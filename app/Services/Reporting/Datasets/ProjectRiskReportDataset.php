<?php

namespace App\Services\Reporting\Datasets;

use App\Models\ProjectRisk;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProjectRiskReportDataset implements ReportDatasetInterface
{
    use BuildsSimpleAggregateQuery;

    public function label(): string
    {
        return 'Project Risk';
    }

    public function nativeFields(): array
    {
        return [
            'Risk' => [
                ['key' => 'title', 'label' => 'Title', 'type' => 'string'],
                ['key' => 'description', 'label' => 'Description', 'type' => 'string'],
                ['key' => 'severity', 'label' => 'Severity', 'type' => 'string'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
            ],
            'Project' => [
                ['key' => 'project_title', 'label' => 'Project', 'type' => 'string'],
                ['key' => 'milestone_title', 'label' => 'Milestone', 'type' => 'string'],
            ],
            'People' => [
                ['key' => 'raised_by_name', 'label' => 'Raised By', 'type' => 'string'],
                ['key' => 'resolved_by_name', 'label' => 'Resolved By', 'type' => 'string'],
            ],
            'Schedule' => [
                ['key' => 'resolved_at', 'label' => 'Resolved At', 'type' => 'date'],
                ['key' => 'created_at', 'label' => 'Created At', 'type' => 'date'],
            ],
        ];
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return ProjectRisk::query()
            ->with(['project', 'milestone', 'raisedByAdmin', 'resolvedByAdmin'])
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
            ->when(filled($search), fn ($query) => $query->where('title', 'like', '%'.$search.'%'));
    }

    /**
     * @param  ProjectRisk  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $all = [
            'title' => $record->title,
            'description' => $record->description,
            'severity' => $record->severity,
            'status' => $record->status,
            'project_title' => $record->project?->title,
            'milestone_title' => $record->milestone?->title,
            'raised_by_name' => $record->raisedByAdmin?->displayName(),
            'resolved_by_name' => $record->resolvedByAdmin?->displayName(),
            'resolved_at' => $record->resolved_at?->toIso8601String(),
            'created_at' => $record->created_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new ProjectRisk())->getMorphClass();
    }

    public function fieldableId(Model $record): int
    {
        return $record->getKey();
    }

    public function groupableFields(): array
    {
        return [
            ['key' => 'severity', 'label' => 'Severity', 'type' => 'string'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
        ];
    }

    public function groupableColumn(string $key): string
    {
        return $this->resolveGroupableColumn([
            'severity' => 'severity',
            'status' => 'status',
        ], $key);
    }

    public function aggregateQuery(?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $this->simpleAggregateQuery(ProjectRisk::class, 'created_at', $start, $end);
    }
}
