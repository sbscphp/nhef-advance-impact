<?php

namespace App\Services\Reporting\Datasets;

use App\Models\Donation;
use App\Models\DonationPayment;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DonationReportDataset implements ReportDatasetInterface
{
    use BuildsSimpleAggregateQuery;

    public function label(): string
    {
        return 'Donations';
    }

    public function nativeFields(): array
    {
        return [
            'Donor' => [
                ['key' => 'donor_name', 'label' => 'Donor Name', 'type' => 'string'],
                ['key' => 'donor_email', 'label' => 'Donor Email', 'type' => 'string'],
            ],
            'Donation' => [
                ['key' => 'campaign_title', 'label' => 'Campaign', 'type' => 'string'],
                ['key' => 'amount', 'label' => 'Amount', 'type' => 'number'],
                ['key' => 'currency', 'label' => 'Currency', 'type' => 'string'],
                ['key' => 'frequency', 'label' => 'Frequency', 'type' => 'string'],
                ['key' => 'donation_status', 'label' => 'Donation Status', 'type' => 'string'],
            ],
            'Payment' => [
                ['key' => 'payment_status', 'label' => 'Payment Status', 'type' => 'string'],
                ['key' => 'gateway', 'label' => 'Gateway', 'type' => 'string'],
                ['key' => 'paid_at', 'label' => 'Paid At', 'type' => 'date'],
                ['key' => 'created_at', 'label' => 'Created At', 'type' => 'date'],
            ],
        ];
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return DonationPayment::query()
            ->with(['donation.campaign', 'donation.user'])
            ->when($start !== null, fn ($query) => $query->where('donation_payments.created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('donation_payments.created_at', '<=', $end))
            ->when(filled($search), function ($query) use ($search): void {
                $query->whereHas('donation', function ($inner) use ($search): void {
                    $inner->where('guest_name', 'like', '%'.$search.'%')
                        ->orWhere('guest_email', 'like', '%'.$search.'%')
                        ->orWhereHas('user', fn ($u) => $u->where('firstname', 'like', '%'.$search.'%')->orWhere('lastname', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
                });
            });
    }

    /**
     * @param  DonationPayment  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $donation = $record->donation;

        $all = [
            'donor_name' => $donation?->donorName(),
            'donor_email' => $donation?->donorEmail(),
            'campaign_title' => $donation?->campaign?->title,
            'amount' => (string) $record->amount,
            'currency' => $record->currency,
            'frequency' => $donation?->frequency,
            'donation_status' => $donation?->status,
            'payment_status' => $record->status,
            'gateway' => $record->gateway,
            'paid_at' => $record->paid_at?->toIso8601String(),
            'created_at' => $record->created_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new Donation())->getMorphClass();
    }

    public function fieldableId(Model $record): int
    {
        return $record->donation_id;
    }

    public function groupableFields(): array
    {
        return [
            ['key' => 'currency', 'label' => 'Currency', 'type' => 'string'],
            ['key' => 'gateway', 'label' => 'Gateway', 'type' => 'string'],
            ['key' => 'payment_status', 'label' => 'Payment Status', 'type' => 'string'],
        ];
    }

    public function groupableColumn(string $key): string
    {
        return $this->resolveGroupableColumn([
            'currency' => 'currency',
            'gateway' => 'gateway',
            'payment_status' => 'status',
        ], $key);
    }

    public function aggregateQuery(?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $this->simpleAggregateQuery(DonationPayment::class, 'created_at', $start, $end);
    }
}
