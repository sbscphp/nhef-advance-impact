<?php

namespace App\Services\Reporting\Datasets;

use App\Models\EventWaitlistEntry;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EventWaitlistReportDataset implements ReportDatasetInterface
{
    use BuildsSimpleAggregateQuery;

    public function label(): string
    {
        return 'Event Waitlist';
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
            ],
            'Waitlist' => [
                ['key' => 'quantity_requested', 'label' => 'Quantity Requested', 'type' => 'number'],
                ['key' => 'position', 'label' => 'Position', 'type' => 'number'],
                ['key' => 'projected_value', 'label' => 'Projected Value', 'type' => 'number'],
                ['key' => 'currency', 'label' => 'Currency', 'type' => 'string'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
            ],
            'Dates' => [
                ['key' => 'created_at', 'label' => 'Joined Waitlist At', 'type' => 'date'],
            ],
        ];
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return EventWaitlistEntry::query()
            ->with(['event', 'user'])
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
     * @param  EventWaitlistEntry  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $all = [
            'event_title' => $record->event?->title,
            'attendee_name' => $record->attendeeName(),
            'attendee_email' => $record->attendeeEmail(),
            'quantity_requested' => $record->quantity_requested,
            'position' => $record->position,
            'projected_value' => (string) $record->projected_value,
            'currency' => $record->currency,
            'status' => $record->status,
            'created_at' => $record->created_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new EventWaitlistEntry())->getMorphClass();
    }

    public function fieldableId(Model $record): int
    {
        return $record->getKey();
    }

    public function groupableFields(): array
    {
        return [
            ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
        ];
    }

    public function groupableColumn(string $key): string
    {
        return $this->resolveGroupableColumn([
            'status' => 'status',
        ], $key);
    }

    public function aggregateQuery(?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $this->simpleAggregateQuery(EventWaitlistEntry::class, 'created_at', $start, $end);
    }
}
