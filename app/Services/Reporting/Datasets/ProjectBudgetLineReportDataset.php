<?php

namespace App\Services\Reporting\Datasets;

use App\Models\ProjectBudgetLine;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProjectBudgetLineReportDataset implements ReportDatasetInterface
{
    use BuildsSimpleAggregateQuery;

    public function label(): string
    {
        return 'Project Budget Line';
    }

    public function nativeFields(): array
    {
        return [
            'Budget Line' => [
                ['key' => 'title', 'label' => 'Title', 'type' => 'string'],
                ['key' => 'reference_id', 'label' => 'Reference ID', 'type' => 'string'],
                ['key' => 'amount_allocated', 'label' => 'Amount Allocated', 'type' => 'number'],
            ],
            'Project' => [
                ['key' => 'project_title', 'label' => 'Project', 'type' => 'string'],
            ],
            'Schedule' => [
                ['key' => 'starts_at', 'label' => 'Starts At', 'type' => 'date'],
                ['key' => 'due_at', 'label' => 'Due At', 'type' => 'date'],
                ['key' => 'created_at', 'label' => 'Created At', 'type' => 'date'],
            ],
        ];
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return ProjectBudgetLine::query()
            ->with('project')
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
            ->when(filled($search), fn ($query) => $query->where('title', 'like', '%'.$search.'%'));
    }

    /**
     * @param  ProjectBudgetLine  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $all = [
            'title' => $record->title,
            'reference_id' => $record->reference_id,
            'amount_allocated' => (string) $record->amount_allocated,
            'project_title' => $record->project?->title,
            'starts_at' => $record->starts_at?->toDateString(),
            'due_at' => $record->due_at?->toDateString(),
            'created_at' => $record->created_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new ProjectBudgetLine())->getMorphClass();
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
        return $this->simpleAggregateQuery(ProjectBudgetLine::class, 'created_at', $start, $end);
    }
}
