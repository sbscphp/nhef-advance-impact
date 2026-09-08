<?php

namespace App\Services\Reporting\Datasets;

use App\Models\NetworkingChannel;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NetworkingReportDataset implements ReportDatasetInterface
{
    use BuildsSimpleAggregateQuery;

    public function label(): string
    {
        return 'Networking';
    }

    public function nativeFields(): array
    {
        return [
            'Channel' => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'string'],
                ['key' => 'type', 'label' => 'Type', 'type' => 'string'],
                ['key' => 'is_archived', 'label' => 'Archived', 'type' => 'boolean'],
                ['key' => 'created_by', 'label' => 'Created By', 'type' => 'string'],
                ['key' => 'members_count', 'label' => 'Members', 'type' => 'number'],
                ['key' => 'messages_count', 'label' => 'Messages', 'type' => 'number'],
            ],
            'Dates' => [
                ['key' => 'created_at', 'label' => 'Created At', 'type' => 'date'],
            ],
        ];
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return NetworkingChannel::query()
            ->with('createdByAdmin')
            ->withCount(['members', 'messages'])
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
            ->when(filled($search), fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
    }

    /**
     * @param  NetworkingChannel  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $all = [
            'name' => $record->name,
            'type' => $record->type,
            'is_archived' => (bool) $record->is_archived,
            'created_by' => $record->createdByAdmin?->displayName(),
            'members_count' => $record->members_count,
            'messages_count' => $record->messages_count,
            'created_at' => $record->created_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new NetworkingChannel())->getMorphClass();
    }

    public function fieldableId(Model $record): int
    {
        return $record->getKey();
    }

    public function groupableFields(): array
    {
        return [
            ['key' => 'type', 'label' => 'Type', 'type' => 'string'],
            ['key' => 'is_archived', 'label' => 'Archived', 'type' => 'boolean'],
        ];
    }

    public function groupableColumn(string $key): string
    {
        return $this->resolveGroupableColumn([
            'type' => 'type',
            'is_archived' => 'is_archived',
        ], $key);
    }

    public function aggregateQuery(?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $this->simpleAggregateQuery(NetworkingChannel::class, 'created_at', $start, $end);
    }
}
