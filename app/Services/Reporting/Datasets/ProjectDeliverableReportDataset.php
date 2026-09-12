<?php

namespace App\Services\Reporting\Datasets;

use App\Models\ProjectDeliverable;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProjectDeliverableReportDataset implements ReportDatasetInterface
{
    use BuildsSimpleAggregateQuery;

    public function label(): string
    {
        return 'Project Deliverable';
    }

    public function nativeFields(): array
    {
        return [
            'Deliverable' => [
                ['key' => 'title', 'label' => 'Title', 'type' => 'string'],
                ['key' => 'is_completed', 'label' => 'Completed', 'type' => 'boolean'],
            ],
            'Project' => [
                ['key' => 'project_title', 'label' => 'Project', 'type' => 'string'],
                ['key' => 'milestone_title', 'label' => 'Milestone', 'type' => 'string'],
            ],
            'Schedule' => [
                ['key' => 'starts_at', 'label' => 'Starts At', 'type' => 'date'],
                ['key' => 'due_at', 'label' => 'Due At', 'type' => 'date'],
                ['key' => 'completed_at', 'label' => 'Completed At', 'type' => 'date'],
                ['key' => 'created_at', 'label' => 'Created At', 'type' => 'date'],
            ],
        ];
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return ProjectDeliverable::query()
            ->with(['project', 'milestone'])
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
            ->when(filled($search), fn ($query) => $query->where('title', 'like', '%'.$search.'%'));
    }

    /**
     * @param  ProjectDeliverable  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $all = [
            'title' => $record->title,
            'is_completed' => (bool) $record->is_completed,
            'project_title' => $record->project?->title,
            'milestone_title' => $record->milestone?->title,
            'starts_at' => $record->starts_at?->toDateString(),
            'due_at' => $record->due_at?->toDateString(),
            'completed_at' => $record->completed_at?->toIso8601String(),
            'created_at' => $record->created_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new ProjectDeliverable())->getMorphClass();
    }

    public function fieldableId(Model $record): int
    {
        return $record->getKey();
    }

    public function groupableFields(): array
    {
        return [
            ['key' => 'is_completed', 'label' => 'Completed', 'type' => 'boolean'],
        ];
    }

    public function groupableColumn(string $key): string
    {
        return $this->resolveGroupableColumn([
            'is_completed' => 'is_completed',
        ], $key);
    }

    public function aggregateQuery(?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $this->simpleAggregateQuery(ProjectDeliverable::class, 'created_at', $start, $end);
    }
}
