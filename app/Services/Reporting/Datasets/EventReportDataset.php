<?php

namespace App\Services\Reporting\Datasets;

use App\Http\Resources\Admin\Events\EventTicketSaleResource;
use App\Models\EventRegistration;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Matches the per-event "Ticket Sales" transaction table ({@see EventTicketSaleResource}), one row per completed registration/checkout.
 */
class EventReportDataset implements ReportDatasetInterface
{
    use BuildsSimpleAggregateQuery;

    public function label(): string
    {
        return 'Event';
    }

    public function nativeFields(): array
    {
        return [
            'Event' => [
                ['key' => 'event_title', 'label' => 'Event Title', 'type' => 'string'],
            ],
            'Attendee' => [
                ['key' => 'attendee_name', 'label' => 'Attendee Name', 'type' => 'string'],
                ['key' => 'attendee_email', 'label' => 'Attendee Email', 'type' => 'string'],
                ['key' => 'is_guest', 'label' => 'Guest', 'type' => 'boolean'],
            ],
            'Registration' => [
                ['key' => 'transaction_id', 'label' => 'Transaction ID', 'type' => 'string'],
                ['key' => 'ticket_type', 'label' => 'Ticket Type', 'type' => 'string'],
                ['key' => 'number_of_tickets', 'label' => 'No. of Tickets', 'type' => 'number'],
                ['key' => 'amount', 'label' => 'Amount', 'type' => 'number'],
                ['key' => 'currency', 'label' => 'Currency', 'type' => 'string'],
                ['key' => 'status', 'label' => 'Registration Status', 'type' => 'string'],
                ['key' => 'paid_via', 'label' => 'Paid Via', 'type' => 'string'],
                ['key' => 'purchase_date', 'label' => 'Purchase Date', 'type' => 'date'],
            ],
        ];
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return EventRegistration::query()
            ->with(['event', 'user', 'items.ticketType', 'payments'])
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('guest_name', 'like', '%'.$search.'%')
                        ->orWhere('guest_email', 'like', '%'.$search.'%')
                        ->orWhereHas('user', fn ($u) => $u->where('firstname', 'like', '%'.$search.'%')->orWhere('lastname', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
                });
            });
    }

    /**
     * @param  EventRegistration  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $items = $record->relationLoaded('items') ? $record->items : collect();
        $ticketTypeNames = $items instanceof SupportCollection
            ? $items->map(fn ($item) => $item->ticketType?->name)->filter()->unique()->values()
            : collect();
        $successfulPayment = $record->relationLoaded('payments') ? $record->payments->firstWhere('status', 'successful') : null;

        $all = [
            'event_title' => $record->event?->title,
            'attendee_name' => $record->attendeeName(),
            'attendee_email' => $record->attendeeEmail(),
            'is_guest' => $record->isGuest(),
            'transaction_id' => 'TRN-'.strtoupper(substr($record->uuid, 0, 8)),
            'ticket_type' => $ticketTypeNames->count() === 1 ? $ticketTypeNames->first() : ($ticketTypeNames->count() > 1 ? 'Multiple' : null),
            'number_of_tickets' => $items instanceof SupportCollection ? (int) $items->sum('quantity') : null,
            'amount' => (string) $record->amount,
            'currency' => $record->currency,
            'status' => $record->status,
            'paid_via' => $successfulPayment !== null ? ($successfulPayment->method ?? $successfulPayment->gateway) : null,
            'purchase_date' => $record->completed_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new EventRegistration)->getMorphClass();
    }

    public function fieldableId(Model $record): int
    {
        return $record->getKey();
    }

    public function groupableFields(): array
    {
        return [
            ['key' => 'status', 'label' => 'Registration Status', 'type' => 'string'],
            ['key' => 'currency', 'label' => 'Currency', 'type' => 'string'],
        ];
    }

    public function groupableColumn(string $key): string
    {
        return $this->resolveGroupableColumn([
            'status' => 'status',
            'currency' => 'currency',
        ], $key);
    }

    public function aggregateQuery(?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $this->simpleAggregateQuery(EventRegistration::class, 'created_at', $start, $end);
    }
}
