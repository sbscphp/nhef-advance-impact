<?php

namespace App\Services\Events;

use App\Enums\AuditActionEnum;
use App\Enums\CurrencyEnum;
use App\Enums\EventStatusEnum;
use App\Enums\EventTypeEnum;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Helpers\PDFReportHelper;
use App\Jobs\SendEventReminderJob;
use App\Models\Admin;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventTicketType;
use App\Repositories\Contracts\Event\EventRegistrationRepositoryInterface;
use App\Repositories\Contracts\Event\EventRepositoryInterface;
use App\Repositories\Contracts\Event\EventTicketTypeRepositoryInterface;
use App\Repositories\Contracts\Event\EventWaitlistEntryRepositoryInterface;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminEventService
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventTicketTypeRepositoryInterface $ticketTypeRepository,
        private readonly EventRegistrationRepositoryInterface $registrationRepository,
        private readonly EventWaitlistEntryRepositoryInterface $waitlistRepository,
        private readonly PDFReportHelper $pdfReportHelper,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, Admin $actor, Request $request): Event
    {
        $bannerUrl = FileUploadHelper::smartSingleFileUpload($payload['banner_image'] ?? null, 'events/banners');
        $eventType = (string) $payload['event_type'];

        $event = DB::transaction(function () use ($payload, $bannerUrl, $eventType, $actor) {
            $event = $this->eventRepository->create([
                'title' => $payload['title'],
                'slug' => $this->uniqueSlug((string) $payload['title']),
                'description' => $payload['description'] ?? null,
                'cover_image_url' => $bannerUrl,
                'event_type' => $eventType,
                'virtual_link' => $eventType === EventTypeEnum::VIRTUAL->value ? ($payload['virtual_link'] ?? null) : null,
                'venue_name' => $eventType === EventTypeEnum::PHYSICAL->value ? ($payload['venue_name'] ?? null) : null,
                'venue_address' => $eventType === EventTypeEnum::PHYSICAL->value ? ($payload['venue_address'] ?? null) : null,
                'starts_at' => $payload['starts_at'],
                'ends_at' => $payload['ends_at'] ?? null,
                'registration_ends_at' => $payload['registration_ends_at'] ?? null,
                'waitlist_enabled' => (bool) ($payload['waitlist_enabled'] ?? false),
                'capacity' => 0,
                'seats_taken' => 0,
                'status' => EventStatusEnum::PUBLISHED->value,
                'created_by' => $actor->uuid,
            ]);

            $ticketRows = $this->buildTicketTypeRows((bool) ($payload['is_paid'] ?? true), (array) $payload['tickets']);
            $ticketTypes = $this->ticketTypeRepository->createMany($event, $ticketRows);

            $event = $this->eventRepository->update($event, ['capacity' => (int) $ticketTypes->sum('quantity')]);
            $event->setRelation('ticketTypes', $ticketTypes);

            return $event;
        });

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::EVENT_CREATED,
            $request,
            $actor->uuid,
            ['event_uuid' => $event->uuid, 'title' => $event->title, 'ticket_type_count' => $event->ticketTypes->count()],
            $actor->displayName().' created an event: '.$event->title.'.',
            Event::class,
            $event->uuid,
            ModuleEnums::events,
            200,
        );

        return $event;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(string $uuid, array $payload, Admin $actor, Request $request): Event
    {
        $event = $this->findForAdmin($uuid);

        $event = DB::transaction(function () use ($event, $payload) {
            $updates = [];
            foreach (['title', 'description', 'starts_at', 'ends_at', 'registration_ends_at'] as $field) {
                if (array_key_exists($field, $payload)) {
                    $updates[$field] = $payload[$field];
                }
            }

            if (array_key_exists('waitlist_enabled', $payload)) {
                $updates['waitlist_enabled'] = (bool) $payload['waitlist_enabled'];
            }

            if (array_key_exists('event_type', $payload)) {
                $eventType = (string) $payload['event_type'];
                $updates['event_type'] = $eventType;
                $updates['virtual_link'] = $eventType === EventTypeEnum::VIRTUAL->value ? ($payload['virtual_link'] ?? null) : null;
                $updates['venue_name'] = $eventType === EventTypeEnum::PHYSICAL->value ? ($payload['venue_name'] ?? null) : null;
                $updates['venue_address'] = $eventType === EventTypeEnum::PHYSICAL->value ? ($payload['venue_address'] ?? null) : null;
            }

            if (! empty($payload['banner_image'])) {
                $updates['cover_image_url'] = FileUploadHelper::smartSingleFileUpload($payload['banner_image'], 'events/banners');
            }

            if ($updates !== []) {
                $event = $this->eventRepository->update($event, $updates);
            }

            if (array_key_exists('tickets', $payload)) {
                $ticketRows = $this->buildTicketTypeRows((bool) ($payload['is_paid'] ?? true), (array) $payload['tickets']);
                $ticketTypes = $this->ticketTypeRepository->syncForEvent($event, $ticketRows);
                $event = $this->eventRepository->update($event, ['capacity' => (int) $ticketTypes->sum('quantity')]);
                $event->setRelation('ticketTypes', $ticketTypes);
            } else {
                $event->load('ticketTypes');
            }

            return $event;
        });

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::EVENT_UPDATED,
            $request,
            $actor->uuid,
            ['event_uuid' => $event->uuid, 'title' => $event->title],
            $actor->displayName().' updated an event: '.$event->title.'.',
            Event::class,
            $event->uuid,
            ModuleEnums::events,
            200,
        );

        return $event;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->eventRepository->paginateAdmin($filters, $perPage);
    }

    /** Unscoped by status; the admin "View Event" screen must load a deactivated/archived event too. */
    public function findForAdmin(string $uuid): Event
    {
        $event = $this->eventRepository->findByUuid($uuid);

        if (! $event instanceof Event) {
            throw new ApiException('Event not found.', 404);
        }

        return $event;
    }

    public function deactivate(string $uuid, Admin $actor, Request $request): Event
    {
        $event = $this->findForAdmin($uuid);

        if (in_array($event->status, [EventStatusEnum::DEACTIVATED->value, EventStatusEnum::ARCHIVED->value], true)) {
            throw new ApiException('Event is already deactivated or archived.', 422);
        }

        $event = $this->eventRepository->update($event, ['status' => EventStatusEnum::DEACTIVATED->value]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::EVENT_DEACTIVATED,
            $request,
            $actor->uuid,
            ['event_uuid' => $event->uuid, 'title' => $event->title],
            $actor->displayName().' deactivated an event: '.$event->title.'.',
            Event::class,
            $event->uuid,
            ModuleEnums::events,
            200,
        );

        return $event;
    }

    public function reactivate(string $uuid, Admin $actor, Request $request): Event
    {
        $event = $this->findForAdmin($uuid);

        if ($event->status !== EventStatusEnum::DEACTIVATED->value) {
            throw new ApiException('Only a deactivated event can be reactivated.', 422);
        }

        $event = $this->eventRepository->update($event, ['status' => EventStatusEnum::PUBLISHED->value]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::EVENT_REACTIVATED,
            $request,
            $actor->uuid,
            ['event_uuid' => $event->uuid, 'title' => $event->title],
            $actor->displayName().' reactivated an event: '.$event->title.'.',
            Event::class,
            $event->uuid,
            ModuleEnums::events,
            200,
        );

        return $event;
    }

    public function archive(string $uuid, Admin $actor, Request $request): Event
    {
        $event = $this->findForAdmin($uuid);

        if ($event->status === EventStatusEnum::ARCHIVED->value) {
            throw new ApiException('Event is already archived.', 422);
        }

        $event = $this->eventRepository->update($event, ['status' => EventStatusEnum::ARCHIVED->value]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::EVENT_ARCHIVED,
            $request,
            $actor->uuid,
            ['event_uuid' => $event->uuid, 'title' => $event->title],
            $actor->displayName().' archived an event: '.$event->title.'.',
            Event::class,
            $event->uuid,
            ModuleEnums::events,
            200,
        );

        return $event;
    }

    /**
     * Feeds the dashboard "All Event / Ongoing / Completed / Archived" stat cards.
     *
     * @return array{all: int, ongoing: int, completed: int, archived: int}
     */
    public function overview(?CarbonInterface $start, ?CarbonInterface $end): array
    {
        return $this->eventRepository->countByStatusBuckets($start, $end);
    }

    /**
     * @return array<string, mixed>
     */
    public function analytics(Event $event, ?CarbonInterface $start, ?CarbonInterface $end): array
    {
        $ticketTypes = $this->ticketTypeRepository->allForEvent($event);
        $sold = (int) $ticketTypes->sum('quantity_sold');
        $capacity = (int) $event->capacity;
        $remaining = $event->seatsRemaining() ?? max(0, $capacity - $sold);
        $capacityFilledPercentage = $capacity > 0 ? round(($sold / $capacity) * 100, 2) : 0.0;
        $waitlistedCount = $this->waitlistRepository->countForEvent($event);

        $trendStart = $start ?? now()->subDays(6)->startOfDay();
        $trendEnd = $end ?? now()->endOfDay();
        $trendRows = $this->registrationRepository->salesTrend($event, $trendStart, $trendEnd);

        return [
            'overview' => [
                'tickets_sold' => $sold,
                'remaining_tickets' => $remaining,
                'capacity_filled_percentage' => $capacityFilledPercentage,
            ],
            'ticket_demand' => [
                'sold' => $sold,
                'available' => $remaining,
                'waitlisted' => $waitlistedCount,
            ],
            'sales_trend' => [
                'start_date' => $trendStart->toDateString(),
                'end_date' => $trendEnd->toDateString(),
                'points' => $trendRows->map(fn ($row) => [
                    'date' => (string) $row->date,
                    'quantity' => (int) $row->quantity,
                ])->values()->all(),
            ],
            'ticket_type_distribution' => $ticketTypes->map(fn (EventTicketType $ticketType) => [
                'ticket_type' => $ticketType->name,
                'quantity_sold' => (int) $ticketType->quantity_sold,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateTicketSales(Event $event, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->registrationRepository->paginateForEventAdmin($event, $filters, $perPage);
    }

    public function findTicketSale(Event $event, string $uuid): EventRegistration
    {
        $registration = $this->registrationRepository->findByUuidForEventAdmin($event, $uuid);

        if (! $registration instanceof EventRegistration) {
            throw new ApiException('Ticket sale not found.', 404);
        }

        return $registration;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, EventRegistration>, 1: bool}
     */
    public function exportTicketSales(Event $event, array $filters): array
    {
        return $this->registrationRepository->exportForEventAdmin($event, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateWaitlist(Event $event, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->waitlistRepository->paginateForEventAdmin($event, $filters, $perPage);
    }

    public function findWaitlistEntry(Event $event, string $uuid)
    {
        $entry = $this->waitlistRepository->findByUuidForEventAdmin($event, $uuid);

        if ($entry === null) {
            throw new ApiException('Waitlist entry not found.', 404);
        }

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportWaitlist(Event $event, array $filters): array
    {
        return $this->waitlistRepository->exportForEventAdmin($event, $filters);
    }

    /**
     * "Send Event Reminder": queues one {@see SendEventReminderJob} per completed registration
     * (guests get a direct email, registered users a database+mail notification) so the admin
     * request doesn't block on mail delivery for every attendee.
     */
    public function sendReminder(string $uuid, Admin $actor, Request $request): int
    {
        $event = $this->findForAdmin($uuid);
        $registrations = $this->registrationRepository->completedForEvent($event);
        $queuedCount = 0;

        foreach ($registrations as $registration) {
            SendEventReminderJob::dispatch($registration->uuid);
            $queuedCount++;
        }

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::EVENT_REMINDER_SENT,
            $request,
            $actor->uuid,
            ['event_uuid' => $event->uuid, 'recipient_count' => $queuedCount],
            $actor->displayName().' queued an event reminder for: '.$event->title.'.',
            Event::class,
            $event->uuid,
            ModuleEnums::events,
            200,
        );

        return $queuedCount;
    }

    public function downloadReport(string $uuid, Admin $actor, Request $request, string $format = 'pdf'): Response
    {
        $event = $this->findForAdmin($uuid);
        $ticketTypes = $this->ticketTypeRepository->allForEvent($event);

        $headings = ['Ticket name', 'Price', 'Allocation', 'Sold', 'Remaining', 'Discount %'];
        $rows = $ticketTypes->map(fn (EventTicketType $ticketType) => [
            $ticketType->name,
            Money::format($ticketType->price, $ticketType->currency),
            $ticketType->quantity !== null ? (string) $ticketType->quantity : 'Unlimited',
            (string) $ticketType->quantity_sold,
            $ticketType->quantityRemaining() !== null ? (string) $ticketType->quantityRemaining() : 'Unlimited',
            $ticketType->discount_percentage !== null ? (string) $ticketType->discount_percentage.'%' : '-',
        ])->values()->all();

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::EVENT_REPORT_GENERATED,
            $request,
            $actor->uuid,
            ['event_uuid' => $event->uuid, 'title' => $event->title, 'format' => $format],
            $actor->displayName().' downloaded a report for event: '.$event->title.'.',
            Event::class,
            $event->uuid,
            ModuleEnums::events,
            200,
        );

        if ($format === 'csv') {
            return $this->reportCsvResponse($event, $headings, $rows);
        }

        return $this->pdfReportHelper->download(
            rows: $rows,
            headings: $headings,
            title: 'Event report - '.$event->title,
            filename: Str::slug($event->title).'-report-'.now()->format('Y-m-d-His').'.pdf',
            orientation: 'portrait',
            periodStart: $event->starts_at->toDateString(),
            periodEnd: $event->ends_at?->toDateString() ?? $event->starts_at->toDateString(),
        );
    }

    /**
     * @param  list<string>  $headings
     * @param  list<list<string>>  $rows
     */
    private function reportCsvResponse(Event $event, array $headings, array $rows): StreamedResponse
    {
        $filename = Str::slug($event->title).'-report-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($headings, $rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headings);

            foreach ($rows as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $tickets
     * @return list<array<string, mixed>>
     */
    private function buildTicketTypeRows(bool $isPaid, array $tickets): array
    {
        return array_map(function (array $ticket) use ($isPaid) {
            return [
                'uuid' => $ticket['uuid'] ?? null,
                'name' => $ticket['name'],
                'price' => $isPaid ? (float) $ticket['price'] : 0.0,
                'currency' => $ticket['currency'] ?? CurrencyEnum::NGN->value,
                'quantity' => (int) $ticket['quantity'],
                'discount_percentage' => $ticket['discount_percentage'] ?? null,
                'discount_starts_at' => $ticket['discount_starts_at'] ?? null,
                'discount_ends_at' => $ticket['discount_ends_at'] ?? null,
            ];
        }, $tickets);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 2;

        while ($this->eventRepository->slugExists($slug)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
