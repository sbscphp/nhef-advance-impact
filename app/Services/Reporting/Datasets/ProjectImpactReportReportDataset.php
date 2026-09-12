<?php

namespace App\Services\Reporting\Datasets;

use App\Models\ProjectImpactReport;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProjectImpactReportReportDataset implements ReportDatasetInterface
{
    use BuildsSimpleAggregateQuery;

    public function label(): string
    {
        return 'Project Impact Report';
    }

    public function nativeFields(): array
    {
        return [
            'Impact Report' => [
                ['key' => 'title', 'label' => 'Title', 'type' => 'string'],
                ['key' => 'description', 'label' => 'Description', 'type' => 'string'],
                ['key' => 'expenditure_value', 'label' => 'Expenditure Value', 'type' => 'number'],
            ],
            'Project' => [
                ['key' => 'project_title', 'label' => 'Project', 'type' => 'string'],
                ['key' => 'deliverable_title', 'label' => 'Deliverable', 'type' => 'string'],
                ['key' => 'budget_line_title', 'label' => 'Budget Line', 'type' => 'string'],
            ],
            'Schedule' => [
                ['key' => 'report_date', 'label' => 'Report Date', 'type' => 'date'],
                ['key' => 'created_at', 'label' => 'Created At', 'type' => 'date'],
            ],
        ];
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return ProjectImpactReport::query()
            ->with(['project', 'deliverable', 'budgetLine'])
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
            ->when(filled($search), fn ($query) => $query->where('title', 'like', '%'.$search.'%'));
    }

    /**
     * @param  ProjectImpactReport  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $all = [
            'title' => $record->title,
            'description' => $record->description,
            'expenditure_value' => $record->expenditure_value !== null ? (string) $record->expenditure_value : null,
            'project_title' => $record->project?->title,
            'deliverable_title' => $record->deliverable?->title,
            'budget_line_title' => $record->budgetLine?->title,
            'report_date' => $record->report_date?->toDateString(),
            'created_at' => $record->created_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new ProjectImpactReport())->getMorphClass();
    }

    public function fieldableId(Model $record): int
    {
        return $record->getKey();
    }

    public function groupableFields(): array
    {
        return [];
    }

    public function groupableColumn(string $key): string
    {
        return $this->resolveGroupableColumn([], $key);
    }

    public function aggregateQuery(?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $this->simpleAggregateQuery(ProjectImpactReport::class, 'created_at', $start, $end);
    }
}
