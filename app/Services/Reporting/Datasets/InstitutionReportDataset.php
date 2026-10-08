<?php

namespace App\Services\Reporting\Datasets;

use App\Models\Institution;
use App\Services\ConstituentManagement\InstitutionStatsLoader;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class InstitutionReportDataset implements HasMonetaryFields, PreparesReportRecords, ReportDatasetInterface
{
    use BuildsSimpleAggregateQuery;

    public function __construct(private readonly InstitutionStatsLoader $statsLoader) {}

    public function label(): string
    {
        return 'Institution';
    }

    public function monetaryFieldKeys(): array
    {
        return ['total_donations', 'total_pledges'];
    }

    public function nativeFields(): array
    {
        return [
            'Identity' => [
                ['key' => 'institution_id', 'label' => 'Institution ID', 'type' => 'string'],
                ['key' => 'name', 'label' => 'Institution Name', 'type' => 'string'],
            ],
            'Contact' => [
                ['key' => 'email', 'label' => 'Email', 'type' => 'string'],
                ['key' => 'phone_number', 'label' => 'Phone Number', 'type' => 'string'],
                ['key' => 'state', 'label' => 'State', 'type' => 'string'],
                ['key' => 'country', 'label' => 'Country', 'type' => 'string'],
            ],
            'Constituents' => [
                ['key' => 'alumni_count', 'label' => 'Alumni', 'type' => 'number'],
                ['key' => 'non_alumni_count', 'label' => 'Non-Alumni', 'type' => 'number'],
                ['key' => 'organisation_count', 'label' => 'Organisation', 'type' => 'number'],
            ],
            'Giving' => [
                ['key' => 'campaigns_count', 'label' => 'Campaigns', 'type' => 'number'],
                ['key' => 'donors_count', 'label' => 'Donors', 'type' => 'number'],
                ['key' => 'total_donations', 'label' => 'Total Donation', 'type' => 'number'],
                ['key' => 'total_pledges', 'label' => 'Total Pledges', 'type' => 'number'],
            ],
            'Status' => [
                ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
                ['key' => 'invited_at', 'label' => 'Invited Date', 'type' => 'date'],
                ['key' => 'onboarded_at', 'label' => 'Onboarded Date', 'type' => 'date'],
            ],
        ];
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return Institution::query()
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            });
    }

    public function prepareRecords(Collection $records): void
    {
        $this->statsLoader->attach($records);
    }

    /**
     * @param  Institution  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $all = [
            'institution_id' => $record->code(),
            'name' => $record->name,
            'email' => $record->email,
            'phone_number' => $record->phone_number,
            'state' => $record->state,
            'country' => $record->country,
            ...$record->statsPayload(),
            ...$record->moneyStatsPayload(),
            'status' => $record->status,
            'invited_at' => $record->invited_at?->toIso8601String(),
            'onboarded_at' => $record->onboarded_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new Institution())->getMorphClass();
    }

    public function fieldableId(Model $record): int
    {
        return $record->getKey();
    }

    public function groupableFields(): array
    {
        return [
            ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
            ['key' => 'state', 'label' => 'State', 'type' => 'string'],
        ];
    }

    public function groupableColumn(string $key): string
    {
        return $this->resolveGroupableColumn([
            'status' => 'status',
            'state' => 'state',
        ], $key);
    }

    public function aggregateQuery(?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $this->simpleAggregateQuery(Institution::class, 'created_at', $start, $end);
    }
}
