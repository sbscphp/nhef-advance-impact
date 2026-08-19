<?php

namespace App\Http\Controllers\v1\Admin\Events;

use App\Helpers\GeneralHelper;
use App\Helpers\PDFReportHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DateRangeStatsRequest;
use App\Http\Requests\Admin\Events\CreateEventRequest;
use App\Http\Requests\Admin\Events\EventListRequest;
use App\Http\Requests\Admin\Events\EventReportRequest;
use App\Http\Requests\Admin\Events\EventTicketSalesListRequest;
use App\Http\Requests\Admin\Events\EventWaitlistListRequest;
use App\Http\Requests\Admin\Events\UpdateEventRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Http\Resources\Admin\Events\EventAdminResource;
use App\Http\Resources\Admin\Events\EventDetailResource;
use App\Http\Resources\Admin\Events\EventTicketSaleResource;
use App\Http\Resources\Admin\Events\EventWaitlistEntryResource;
use App\Models\Admin;
use App\Models\EventRegistration;
use App\Models\EventWaitlistEntry;
use App\Responser\JsonResponser;
use App\Services\Events\AdminEventService;
use App\Support\ListingQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventController extends Controller
{
    public function __construct(private readonly AdminEventService $eventService) {}

    public function store(CreateEventRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $event = $this->eventService->create($request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Event created successfully.', EventDetailResource::make($event)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@store');
        }
    }

    public function index(EventListRequest $request)
    {
        try {
            $paginator = $this->eventService->paginateForAdmin($request->validated());

            return JsonResponser::send(false, 'Events retrieved.', $this->paginatedPayload($paginator, EventAdminResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@index');
        }
    }

    public function overview(DateRangeStatsRequest $request)
    {
        try {
            $window = ListingFilterRules::resolveDateWindow($request->validated());
            $overview = $this->eventService->overview($window['start'], $window['end']);

            return JsonResponser::send(false, 'Event overview retrieved.', $overview);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@overview');
        }
    }

    public function show(string $uuid)
    {
        try {
            $event = $this->eventService->findForAdmin($uuid);
            $event->load('ticketTypes');

            return JsonResponser::send(false, 'Event retrieved.', EventDetailResource::make($event)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@show');
        }
    }

    public function update(UpdateEventRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $event = $this->eventService->update($uuid, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Event updated.', EventDetailResource::make($event)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@update');
        }
    }

    public function deactivate(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $event = $this->eventService->deactivate($uuid, $admin, $request);

            return JsonResponser::send(false, 'Event deactivated.', EventAdminResource::make($event)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@deactivate');
        }
    }

    public function reactivate(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $event = $this->eventService->reactivate($uuid, $admin, $request);

            return JsonResponser::send(false, 'Event reactivated.', EventAdminResource::make($event)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@reactivate');
        }
    }

    public function archive(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $event = $this->eventService->archive($uuid, $admin, $request);

            return JsonResponser::send(false, 'Event archived.', EventAdminResource::make($event)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@archive');
        }
    }

    public function sendReminder(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $queuedCount = $this->eventService->sendReminder($uuid, $admin, $request);

            return JsonResponser::send(false, 'Event reminder queued.', ['recipient_count' => $queuedCount]);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@sendReminder');
        }
    }

    public function downloadReport(EventReportRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $format = $request->validated('export') ?? 'pdf';

            return $this->eventService->downloadReport($uuid, $admin, $request, $format);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@downloadReport');
        }
    }

    public function analytics(DateRangeStatsRequest $request, string $uuid)
    {
        try {
            $event = $this->eventService->findForAdmin($uuid);
            $window = ListingFilterRules::resolveDateWindow($request->validated());
            $analytics = $this->eventService->analytics($event, $window['start'], $window['end']);

            return JsonResponser::send(false, 'Event analytics retrieved.', $analytics);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@analytics');
        }
    }

    public function ticketSales(EventTicketSalesListRequest $request, string $uuid)
    {
        try {
            $event = $this->eventService->findForAdmin($uuid);
            $export = $request->validated('export');

            if ($export === 'csv') {
                return $this->respondTicketSalesCsv($event->uuid, $request->validated());
            }

            if ($export === 'pdf') {
                return $this->respondTicketSalesPdf($event->uuid, $request->validated());
            }

            $paginator = $this->eventService->paginateTicketSales($event, $request->validated());

            return JsonResponser::send(false, 'Ticket sales retrieved.', $this->paginatedPayload($paginator, EventTicketSaleResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@ticketSales');
        }
    }

    public function ticketSale(string $uuid, string $saleUuid)
    {
        try {
            $event = $this->eventService->findForAdmin($uuid);
            $sale = $this->eventService->findTicketSale($event, $saleUuid);

            return JsonResponser::send(false, 'Ticket sale retrieved.', EventTicketSaleResource::make($sale)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@ticketSale');
        }
    }

    public function waitlist(EventWaitlistListRequest $request, string $uuid)
    {
        try {
            $event = $this->eventService->findForAdmin($uuid);
            $export = $request->validated('export');

            if ($export === 'csv') {
                return $this->respondWaitlistCsv($event->uuid, $request->validated());
            }

            if ($export === 'pdf') {
                return $this->respondWaitlistPdf($event->uuid, $request->validated());
            }

            $paginator = $this->eventService->paginateWaitlist($event, $request->validated());

            return JsonResponser::send(false, 'Waitlist retrieved.', $this->paginatedPayload($paginator, EventWaitlistEntryResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@waitlist');
        }
    }

    public function waitlistEntry(string $uuid, string $entryUuid)
    {
        try {
            $event = $this->eventService->findForAdmin($uuid);
            $entry = $this->eventService->findWaitlistEntry($event, $entryUuid);

            return JsonResponser::send(false, 'Waitlist entry retrieved.', EventWaitlistEntryResource::make($entry)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@waitlistEntry');
        }
    }

    private function respondTicketSalesCsv(string $eventUuid, array $filters): StreamedResponse
    {
        $event = $this->eventService->findForAdmin($eventUuid);
        [$rows, $truncated] = $this->eventService->exportTicketSales($event, $filters);
        $filename = 'ticket-sales-'.$event->slug.'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Transaction ID', 'Purchase Date', 'Ticket Type', 'No. of Ticket', 'Ticket Value', 'Status', 'Attendee', 'Email']);

            /** @var EventRegistration $registration */
            foreach ($rows as $registration) {
                $ticketTypeNames = $registration->items->map(fn ($item) => $item->ticketType?->name)->filter()->unique()->implode(', ');
                fputcsv($out, [
                    'TRN-'.strtoupper(substr($registration->uuid, 0, 8)),
                    $registration->completed_at?->toIso8601String(),
                    $ticketTypeNames,
                    (int) $registration->items->sum('quantity'),
                    (string) $registration->amount,
                    $registration->status,
                    $registration->attendeeName(),
                    $registration->attendeeEmail(),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Export-Truncated' => $truncated ? '1' : '0',
        ]);
    }

    private function respondTicketSalesPdf(string $eventUuid, array $filters)
    {
        $event = $this->eventService->findForAdmin($eventUuid);
        [$rows, $truncated] = $this->eventService->exportTicketSales($event, $filters);
        $listing = ListingQuery::fromValidated($filters);

        $headings = ['Transaction ID', 'Purchase Date', 'Ticket Type', 'No. of Ticket', 'Ticket Value', 'Status'];
        $tableRows = $rows->map(fn (EventRegistration $registration): array => [
            'TRN-'.strtoupper(substr($registration->uuid, 0, 8)),
            $registration->completed_at?->format('Y-m-d H:i') ?? '',
            $registration->items->map(fn ($item) => $item->ticketType?->name)->filter()->unique()->implode(', '),
            (string) $registration->items->sum('quantity'),
            (string) $registration->amount,
            $registration->status,
        ]);

        return app(PDFReportHelper::class)->download(
            rows: $tableRows,
            headings: $headings,
            title: 'Ticket sales - '.$event->title,
            filename: 'ticket-sales-'.$event->slug.'-'.now()->format('Y-m-d-His').'.pdf',
            orientation: 'landscape',
            periodStart: $listing->startDate?->toDateString() ?? 'All dates',
            periodEnd: $listing->endDate?->toDateString() ?? 'All dates',
            truncated: $truncated,
            includedRows: $tableRows->count(),
        );
    }

    private function respondWaitlistCsv(string $eventUuid, array $filters): StreamedResponse
    {
        $event = $this->eventService->findForAdmin($eventUuid);
        [$rows, $truncated] = $this->eventService->exportWaitlist($event, $filters);
        $filename = 'waitlist-'.$event->slug.'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Waitlist ID', 'Attendee Name', 'Email Address', 'Ticket Requested', 'No. of Ticket', 'Waitlist Position', 'Status']);

            /** @var EventWaitlistEntry $entry */
            foreach ($rows as $entry) {
                fputcsv($out, [
                    'WA-'.strtoupper(substr($entry->uuid, 0, 6)),
                    $entry->attendeeName(),
                    $entry->attendeeEmail(),
                    $entry->ticketType?->name,
                    $entry->quantity_requested,
                    $entry->position,
                    $entry->status,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Export-Truncated' => $truncated ? '1' : '0',
        ]);
    }

    private function respondWaitlistPdf(string $eventUuid, array $filters)
    {
        $event = $this->eventService->findForAdmin($eventUuid);
        [$rows, $truncated] = $this->eventService->exportWaitlist($event, $filters);
        $listing = ListingQuery::fromValidated($filters);

        $headings = ['Waitlist ID', 'Attendee Name', 'Email Address', 'Ticket Requested', 'No. of Ticket', 'Position', 'Status'];
        $tableRows = $rows->map(fn (EventWaitlistEntry $entry): array => [
            'WA-'.strtoupper(substr($entry->uuid, 0, 6)),
            $entry->attendeeName(),
            $entry->attendeeEmail(),
            (string) $entry->ticketType?->name,
            (string) $entry->quantity_requested,
            (string) $entry->position,
            $entry->status,
        ]);

        return app(PDFReportHelper::class)->download(
            rows: $tableRows,
            headings: $headings,
            title: 'Waitlist - '.$event->title,
            filename: 'waitlist-'.$event->slug.'-'.now()->format('Y-m-d-His').'.pdf',
            orientation: 'landscape',
            periodStart: $listing->startDate?->toDateString() ?? 'All dates',
            periodEnd: $listing->endDate?->toDateString() ?? 'All dates',
            truncated: $truncated,
            includedRows: $tableRows->count(),
        );
    }

    /**
     * @param  class-string<JsonResource>  $resourceClass
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator, string $resourceClass): array
    {
        $payload = $paginator->toArray();
        /** @var AnonymousResourceCollection $resource */
        $resource = $resourceClass::collection($paginator);
        $payload['data'] = $resource->resolve();

        return $payload;
    }

    private function requireAdmin(Request $request): Admin
    {
        $admin = $request->user();
        if (! $admin instanceof Admin) {
            abort(403, 'Forbidden.');
        }

        return $admin;
    }
}
