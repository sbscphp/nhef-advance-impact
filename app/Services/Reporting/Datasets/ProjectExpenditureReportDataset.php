<?php

namespace App\Services\Reporting\Datasets;

use App\Models\ProjectExpenditure;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProjectExpenditureReportDataset implements ReportDatasetInterface
{
    use BuildsSimpleAggregateQuery;

    public function label(): string
    {
        return 'Project Expenditure';
    }

    public function nativeFields(): array
    {
        return [
            'Expenditure' => [
                ['key' => 'title', 'label' => 'Title', 'type' => 'string'],
                ['key' => 'description', 'label' => 'Description', 'type' => 'string'],
                ['key' => 'amount', 'label' => 'Amount', 'type' => 'number'],
                ['key' => 'reference_id', 'label' => 'Reference ID', 'type' => 'string'],
            ],
            'Project' => [
                ['key' => 'project_title', 'label' => 'Project', 'type' => 'string'],
                ['key' => 'budget_line_title', 'label' => 'Budget Line', 'type' => 'string'],
            ],
            'Schedule' => [
                ['key' => 'transaction_date', 'label' => 'Transaction Date', 'type' => 'date'],
                ['key' => 'created_at', 'label' => 'Created At', 'type' => 'date'],
            ],
        ];
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return ProjectExpenditure::query()
            ->with(['project', 'budgetLine'])
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
            ->when(filled($search), fn ($query) => $query->where('title', 'like', '%'.$search.'%'));
    }

    /**
     * @param  ProjectExpenditure  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $all = [
            'title' => $record->title,
            'description' => $record->description,
            'amount' => (string) $record->amount,
            'reference_id' => $record->reference_id,
            'project_title' => $record->project?->title,
            'budget_line_title' => $record->budgetLine?->title,
            'transaction_date' => $record->transaction_date?->toDateString(),
            'created_at' => $record->created_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new ProjectExpenditure())->getMorphClass();
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
        return $this->simpleAggregateQuery(ProjectExpenditure::class, 'created_at', $start, $end);
    }
}
