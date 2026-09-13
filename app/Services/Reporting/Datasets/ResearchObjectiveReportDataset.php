<?php

namespace App\Services\Reporting\Datasets;

use App\Models\ResearchObjective;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ResearchObjectiveReportDataset implements ReportDatasetInterface
{
    use BuildsSimpleAggregateQuery;

    public function label(): string
    {
        return 'Research Objective';
    }

    public function nativeFields(): array
    {
        return [
            'Objective' => [
                ['key' => 'title', 'label' => 'Title', 'type' => 'string'],
                ['key' => 'is_completed', 'label' => 'Completed', 'type' => 'boolean'],
            ],
            'Research' => [
                ['key' => 'research_title', 'label' => 'Research', 'type' => 'string'],
            ],
            'Schedule' => [
                ['key' => 'created_at', 'label' => 'Created At', 'type' => 'date'],
            ],
        ];
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return ResearchObjective::query()
            ->with('research')
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
            ->when(filled($search), fn ($query) => $query->where('title', 'like', '%'.$search.'%'));
    }

    /**
     * @param  ResearchObjective  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $all = [
            'title' => $record->title,
            'is_completed' => (bool) $record->is_completed,
            'research_title' => $record->research?->title,
            'created_at' => $record->created_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new ResearchObjective())->getMorphClass();
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
        return $this->simpleAggregateQuery(ResearchObjective::class, 'created_at', $start, $end);
    }
}
