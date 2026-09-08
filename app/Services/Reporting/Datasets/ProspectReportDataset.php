<?php

namespace App\Services\Reporting\Datasets;

use App\Models\Prospect;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProspectReportDataset implements ReportDatasetInterface
{
    use BuildsSimpleAggregateQuery;

    public function label(): string
    {
        return 'CRM Prospects';
    }

    public function nativeFields(): array
    {
        return [
            'Identity' => [
                ['key' => 'full_name', 'label' => 'Full Name', 'type' => 'string'],
                ['key' => 'email', 'label' => 'Email', 'type' => 'string'],
                ['key' => 'phone', 'label' => 'Phone', 'type' => 'string'],
            ],
            'Pipeline' => [
                ['key' => 'lead_source', 'label' => 'Lead Source', 'type' => 'string'],
                ['key' => 'stage', 'label' => 'Pipeline Stage', 'type' => 'string'],
                ['key' => 'estimated_value', 'label' => 'Estimated Value', 'type' => 'number'],
                ['key' => 'currency', 'label' => 'Currency', 'type' => 'string'],
                ['key' => 'assigned_admin', 'label' => 'Assigned Admin', 'type' => 'string'],
            ],
            'Dates' => [
                ['key' => 'stage_entered_at', 'label' => 'Stage Entered At', 'type' => 'date'],
                ['key' => 'created_at', 'label' => 'Created At', 'type' => 'date'],
            ],
        ];
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return Prospect::query()
            ->with('assignedAdmin')
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            });
    }

    /**
     * @param  Prospect  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $all = [
            'full_name' => $record->fullName(),
            'email' => $record->email,
            'phone' => $record->phone,
            'lead_source' => $record->lead_source,
            'stage' => $record->stage,
            'estimated_value' => (string) $record->estimated_value,
            'currency' => $record->currency,
            'assigned_admin' => $record->assignedAdmin?->displayName(),
            'stage_entered_at' => $record->stage_entered_at?->toIso8601String(),
            'created_at' => $record->created_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new Prospect())->getMorphClass();
    }

    public function fieldableId(Model $record): int
    {
        return $record->getKey();
    }

    public function groupableFields(): array
    {
        return [
            ['key' => 'stage', 'label' => 'Pipeline Stage', 'type' => 'string'],
            ['key' => 'lead_source', 'label' => 'Lead Source', 'type' => 'string'],
            ['key' => 'currency', 'label' => 'Currency', 'type' => 'string'],
        ];
    }

    public function groupableColumn(string $key): string
    {
        return $this->resolveGroupableColumn([
            'stage' => 'stage',
            'lead_source' => 'lead_source',
            'currency' => 'currency',
        ], $key);
    }

    public function aggregateQuery(?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $this->simpleAggregateQuery(Prospect::class, 'created_at', $start, $end);
    }
}
