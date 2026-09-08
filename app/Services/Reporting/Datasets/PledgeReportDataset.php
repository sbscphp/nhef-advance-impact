<?php

namespace App\Services\Reporting\Datasets;

use App\Models\Pledge;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PledgeReportDataset implements ReportDatasetInterface
{
    use BuildsSimpleAggregateQuery;

    public function label(): string
    {
        return 'Pledges';
    }

    public function nativeFields(): array
    {
        return [
            'Donor' => [
                ['key' => 'donor_name', 'label' => 'Donor Name', 'type' => 'string'],
                ['key' => 'donor_email', 'label' => 'Donor Email', 'type' => 'string'],
            ],
            'Pledge' => [
                ['key' => 'campaign_title', 'label' => 'Campaign', 'type' => 'string'],
                ['key' => 'total_amount', 'label' => 'Total Amount', 'type' => 'number'],
                ['key' => 'amount_paid', 'label' => 'Amount Paid', 'type' => 'number'],
                ['key' => 'currency', 'label' => 'Currency', 'type' => 'string'],
                ['key' => 'frequency', 'label' => 'Frequency', 'type' => 'string'],
                ['key' => 'installment_count', 'label' => 'Installment Count', 'type' => 'number'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
            ],
            'Dates' => [
                ['key' => 'next_installment_due_at', 'label' => 'Next Installment Due', 'type' => 'date'],
                ['key' => 'completed_at', 'label' => 'Completed At', 'type' => 'date'],
                ['key' => 'created_at', 'label' => 'Created At', 'type' => 'date'],
            ],
        ];
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return Pledge::query()
            ->with('campaign')
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('guest_name', 'like', '%'.$search.'%')
                        ->orWhere('guest_email', 'like', '%'.$search.'%')
                        ->orWhereHas('user', fn ($u) => $u->where('firstname', 'like', '%'.$search.'%')->orWhere('lastname', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
                });
            });
    }

    /**
     * @param  Pledge  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $all = [
            'donor_name' => $record->donorName(),
            'donor_email' => $record->donorEmail(),
            'campaign_title' => $record->campaign?->title,
            'total_amount' => (string) $record->total_amount,
            'amount_paid' => (string) $record->amount_paid,
            'currency' => $record->currency,
            'frequency' => $record->frequency,
            'installment_count' => $record->installment_count,
            'status' => $record->status,
            'next_installment_due_at' => $record->next_installment_due_at?->toDateString(),
            'completed_at' => $record->completed_at?->toIso8601String(),
            'created_at' => $record->created_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new Pledge())->getMorphClass();
    }

    public function fieldableId(Model $record): int
    {
        return $record->getKey();
    }

    public function groupableFields(): array
    {
        return [
            ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
            ['key' => 'frequency', 'label' => 'Frequency', 'type' => 'string'],
            ['key' => 'currency', 'label' => 'Currency', 'type' => 'string'],
        ];
    }

    public function groupableColumn(string $key): string
    {
        return $this->resolveGroupableColumn([
            'status' => 'status',
            'frequency' => 'frequency',
            'currency' => 'currency',
        ], $key);
    }

    public function aggregateQuery(?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $this->simpleAggregateQuery(Pledge::class, 'created_at', $start, $end);
    }
}
