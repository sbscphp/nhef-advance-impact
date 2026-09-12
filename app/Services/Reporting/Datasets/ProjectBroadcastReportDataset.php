<?php

namespace App\Services\Reporting\Datasets;

use App\Models\ProjectBroadcast;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProjectBroadcastReportDataset implements ReportDatasetInterface
{
    use BuildsSimpleAggregateQuery;

    public function label(): string
    {
        return 'Project Broadcast';
    }

    public function nativeFields(): array
    {
        return [
            'Broadcast' => [
                ['key' => 'title', 'label' => 'Title', 'type' => 'string'],
                ['key' => 'delivery_via', 'label' => 'Delivery Via', 'type' => 'string'],
                ['key' => 'all_team_members', 'label' => 'All Team Members', 'type' => 'boolean'],
                ['key' => 'number_of_reach', 'label' => 'Number Reached', 'type' => 'number'],
            ],
            'Project' => [
                ['key' => 'project_title', 'label' => 'Project', 'type' => 'string'],
                ['key' => 'sent_by_name', 'label' => 'Sent By', 'type' => 'string'],
            ],
            'Schedule' => [
                ['key' => 'send_date', 'label' => 'Send Date', 'type' => 'date'],
                ['key' => 'created_at', 'label' => 'Created At', 'type' => 'date'],
            ],
        ];
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return ProjectBroadcast::query()
            ->with(['project', 'sender'])
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
            ->when(filled($search), fn ($query) => $query->where('title', 'like', '%'.$search.'%'));
    }

    /**
     * @param  ProjectBroadcast  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $all = [
            'title' => $record->title,
            'delivery_via' => $record->delivery_via,
            'all_team_members' => (bool) $record->all_team_members,
            'number_of_reach' => (string) $record->number_of_reach,
            'project_title' => $record->project?->title,
            'sent_by_name' => $record->sender?->displayName(),
            'send_date' => $record->send_date?->toDateString(),
            'created_at' => $record->created_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new ProjectBroadcast())->getMorphClass();
    }

    public function fieldableId(Model $record): int
    {
        return $record->getKey();
    }

    public function groupableFields(): array
    {
        return [
            ['key' => 'delivery_via', 'label' => 'Delivery Via', 'type' => 'string'],
            ['key' => 'all_team_members', 'label' => 'All Team Members', 'type' => 'boolean'],
        ];
    }

    public function groupableColumn(string $key): string
    {
        return $this->resolveGroupableColumn([
            'delivery_via' => 'delivery_via',
            'all_team_members' => 'all_team_members',
        ], $key);
    }

    public function aggregateQuery(?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $this->simpleAggregateQuery(ProjectBroadcast::class, 'created_at', $start, $end);
    }
}
