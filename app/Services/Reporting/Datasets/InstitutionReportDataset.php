<?php

namespace App\Services\Reporting\Datasets;

use App\Http\Resources\Admin\ConstituentManagement\InstitutionAdminResource;
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

    /**
     * Matches the Partner Institutions table exactly ({@see InstitutionAdminResource}) - no fields beyond what that screen shows.
     */
    public function nativeFields(): array
    {
        return [
            'Identity' => [
                ['key' => 'institution_id', 'label' => 'Institution ID', 'type' => 'string'],
                ['key' => 'name', 'label' => 'Institution Name', 'type' => 'string'],
                ['key' => 'slug', 'label' => 'Slug', 'type' => 'string'],
                ['key' => 'email', 'label' => 'Email', 'type' => 'string'],
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
                ['key' => 'is_active', 'label' => 'Active', 'type' => 'boolean'],
                ['key' => 'date_added', 'label' => 'Date Added', 'type' => 'date'],
                ['key' => 'date_onboarded', 'label' => 'Onboarded Date', 'type' => 'date'],
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
            'slug' => $record->slug,
            'email' => $record->email,
            ...$record->statsPayload(),
            ...$record->moneyStatsPayload(),
            'status' => $record->status,
            'is_active' => $record->is_active,
            'date_added' => $record->created_at?->toIso8601String(),
            'date_onboarded' => $record->onboarded_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new Institution)->getMorphClass();
    }

    public function fieldableId(Model $record): int
    {
        return $record->getKey();
    }

    public function groupableFields(): array
    {
        return [
            ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
            ['key' => 'is_active', 'label' => 'Active', 'type' => 'boolean'],
        ];
    }

    public function groupableColumn(string $key): string
    {
        return $this->resolveGroupableColumn([
            'status' => 'status',
            'is_active' => 'is_active',
        ], $key);
    }

    public function aggregateQuery(?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $this->simpleAggregateQuery(Institution::class, 'created_at', $start, $end);
    }
}
