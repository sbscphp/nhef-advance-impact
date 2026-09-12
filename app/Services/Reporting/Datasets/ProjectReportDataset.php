<?php

namespace App\Services\Reporting\Datasets;

use App\Models\Project;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProjectReportDataset implements ReportDatasetInterface
{
    use BuildsSimpleAggregateQuery;

    public function label(): string
    {
        return 'Project';
    }

    public function nativeFields(): array
    {
        return [
            'Project' => [
                ['key' => 'title', 'label' => 'Title', 'type' => 'string'],
                ['key' => 'category', 'label' => 'Category', 'type' => 'string'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
                ['key' => 'country', 'label' => 'Country', 'type' => 'string'],
                ['key' => 'state_lga', 'label' => 'State/LGA', 'type' => 'string'],
            ],
            'Budget & Funding' => [
                ['key' => 'currency', 'label' => 'Currency', 'type' => 'string'],
                ['key' => 'approved_budget', 'label' => 'Approved Budget', 'type' => 'number'],
                ['key' => 'funding_received', 'label' => 'Funding Received', 'type' => 'number'],
                ['key' => 'funded_by_donor', 'label' => 'Funded By Donor', 'type' => 'boolean'],
                ['key' => 'funded_by_donation', 'label' => 'Funded By Donation Campaign', 'type' => 'boolean'],
            ],
            'Schedule' => [
                ['key' => 'starts_at', 'label' => 'Starts At', 'type' => 'date'],
                ['key' => 'ends_at', 'label' => 'Ends At', 'type' => 'date'],
                ['key' => 'created_at', 'label' => 'Created At', 'type' => 'date'],
            ],
        ];
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return Project::query()
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
            ->when(filled($search), fn ($query) => $query->where('title', 'like', '%'.$search.'%'));
    }

    /**
     * @param  Project  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $all = [
            'title' => $record->title,
            'category' => $record->category,
            'status' => $record->status,
            'country' => $record->country,
            'state_lga' => $record->state_lga,
            'currency' => $record->currency,
            'approved_budget' => $record->approved_budget !== null ? (string) $record->approved_budget : null,
            'funding_received' => $record->funding_received !== null ? (string) $record->funding_received : null,
            'funded_by_donor' => (bool) $record->funded_by_donor,
            'funded_by_donation' => (bool) $record->funded_by_donation,
            'starts_at' => $record->starts_at?->toDateString(),
            'ends_at' => $record->ends_at?->toDateString(),
            'created_at' => $record->created_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new Project())->getMorphClass();
    }

    public function fieldableId(Model $record): int
    {
        return $record->getKey();
    }

    public function groupableFields(): array
    {
        return [
            ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
            ['key' => 'category', 'label' => 'Category', 'type' => 'string'],
            ['key' => 'currency', 'label' => 'Currency', 'type' => 'string'],
            ['key' => 'country', 'label' => 'Country', 'type' => 'string'],
        ];
    }

    public function groupableColumn(string $key): string
    {
        return $this->resolveGroupableColumn([
            'status' => 'status',
            'category' => 'category',
            'currency' => 'currency',
            'country' => 'country',
        ], $key);
    }

    public function aggregateQuery(?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $this->simpleAggregateQuery(Project::class, 'created_at', $start, $end);
    }
}
