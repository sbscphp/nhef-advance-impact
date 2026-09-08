<?php

namespace App\Services\Reporting\Datasets;

use App\Models\EventRegistration;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
            ],
            'Registration' => [
                ['key' => 'amount', 'label' => 'Amount', 'type' => 'number'],
                ['key' => 'currency', 'label' => 'Currency', 'type' => 'string'],
                ['key' => 'status', 'label' => 'Registration Status', 'type' => 'string'],
                ['key' => 'created_at', 'label' => 'Registered At', 'type' => 'date'],
            ],
        ];
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return EventRegistration::query()
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
     * @param  EventRegistration  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $all = [
            'event_title' => $record->event?->title,
            'attendee_name' => $record->attendeeName(),
            'attendee_email' => $record->attendeeEmail(),
            'amount' => (string) $record->amount,
            'currency' => $record->currency,
            'status' => $record->status,
            'created_at' => $record->created_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new EventRegistration())->getMorphClass();
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
