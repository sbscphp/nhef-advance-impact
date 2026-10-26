<?php

namespace App\Services\Reporting\Datasets;

use App\Http\Resources\Admin\CampaignAdminResource;
use App\Models\Campaign;
use App\Repositories\Contracts\CampaignInstitution\CampaignInstitutionRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CampaignReportDataset implements HasMonetaryFields, PreparesReportRecords, ReportDatasetInterface
{
    use BuildsSimpleAggregateQuery;

    public function __construct(
        private readonly DonationPaymentRepositoryInterface $paymentRepository,
        private readonly CampaignInstitutionRepositoryInterface $campaignInstitutionRepository,
    ) {}

    public function label(): string
    {
        return 'Campaign';
    }

    public function monetaryFieldKeys(): array
    {
        return ['goal_amount', 'raised_amount'];
    }

    /**
     * Matches the Fundraising Campaign list table ({@see CampaignAdminResource}).
     */
    public function nativeFields(): array
    {
        return [
            'Campaign' => [
                ['key' => 'title', 'label' => 'Title', 'type' => 'string'],
                ['key' => 'type', 'label' => 'Type', 'type' => 'string'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
                ['key' => 'institutions', 'label' => 'Campaign By', 'type' => 'string'],
                ['key' => 'goal_amount', 'label' => 'Goal Amount', 'type' => 'number'],
                ['key' => 'raised_amount', 'label' => 'Raised Amount', 'type' => 'number'],
                ['key' => 'progress_percentage', 'label' => 'Progress %', 'type' => 'number'],
                ['key' => 'donors_count', 'label' => 'No. of Donor', 'type' => 'number'],
                ['key' => 'currency', 'label' => 'Currency', 'type' => 'string'],
            ],
            'Schedule' => [
                ['key' => 'starts_at', 'label' => 'Starts At', 'type' => 'date'],
                ['key' => 'ends_at', 'label' => 'Due Date', 'type' => 'date'],
                ['key' => 'created_at', 'label' => 'Created At', 'type' => 'date'],
            ],
        ];
    }

    public function prepareRecords(Collection $records): void
    {
        $ids = $records->pluck('id')->all();
        $payments = $this->paymentRepository->statsByCampaigns($ids);
        $institutionNames = $this->campaignInstitutionRepository->namesByCampaigns($ids);

        foreach ($records as $record) {
            $record->setAttribute('donors_count', $payments[$record->id]['donors'] ?? 0);
            $record->setAttribute('institutions', $institutionNames[$record->id] ?? []);
        }
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return Campaign::query()
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
            ->when(filled($search), fn ($query) => $query->where('title', 'like', '%'.$search.'%'));
    }

    /**
     * @param  Campaign  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $all = [
            'title' => $record->title,
            'type' => $record->type,
            'status' => $record->status,
            'institutions' => implode(', ', array_column($record->getAttribute('institutions') ?? [], 'name')),
            'goal_amount' => $record->goal_amount !== null ? (string) $record->goal_amount : null,
            'raised_amount' => (string) $record->raised_amount,
            'progress_percentage' => $record->progressPercentage(),
            'donors_count' => (int) ($record->getAttribute('donors_count') ?? 0),
            'currency' => $record->currency,
            'starts_at' => $record->starts_at?->toDateString(),
            'ends_at' => $record->ends_at?->toDateString(),
            'created_at' => $record->created_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new Campaign)->getMorphClass();
    }

    public function fieldableId(Model $record): int
    {
        return $record->getKey();
    }

    public function groupableFields(): array
    {
        return [
            ['key' => 'type', 'label' => 'Type', 'type' => 'string'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
            ['key' => 'currency', 'label' => 'Currency', 'type' => 'string'],
        ];
    }

    public function groupableColumn(string $key): string
    {
        return $this->resolveGroupableColumn([
            'type' => 'type',
            'status' => 'status',
            'currency' => 'currency',
        ], $key);
    }

    public function aggregateQuery(?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $this->simpleAggregateQuery(Campaign::class, 'created_at', $start, $end);
    }
}
