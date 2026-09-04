<?php

namespace Database\Seeders;

use App\Enums\CampaignTypeEnum;
use App\Enums\CurrencyEnum;
use App\Enums\PaymentGatewayEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\PledgeFrequencyEnum;
use App\Enums\PledgeInstallmentStatusEnum;
use App\Enums\PledgeStatusEnum;
use App\Models\Campaign;
use App\Models\CampaignInstitution;
use App\Models\DonationPayment;
use App\Models\Pledge;
use App\Models\PledgeInstallment;
use App\Models\PledgePayment;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Concerns\SeedsDonors;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Depends on CampaignSeeder, InstitutionSeeder, and NationalGivingDayCampaignSeeder. Run after
 * DonationSeeder so `raised_amount` reflects both. Reuses DonationSeeder's per-institution
 * donors, since National Giving Day attribution is by the pledgor's `tertiary_institution_id`.
 *
 * php artisan db:seed --class=PledgeSeeder
 */
class PledgeSeeder extends Seeder
{
    use SeedsDonors;

    public function run(): void
    {
        $campaigns = Campaign::query()->with('campaignInstitutions.institution')->get();

        foreach ($campaigns as $campaign) {
            if ($campaign->type === CampaignTypeEnum::NATIONAL_GIVING_DAY->value) {
                $this->seedNationalGivingDay($campaign);
            } else {
                $this->seedStandard($campaign);
            }
        }
    }

    private function seedStandard(Campaign $campaign): void
    {
        $oneTimeDonor = $this->donor('donor.standard.2@yopmail.com', 'Tunde', 'Bakare');
        $recurringDonor = $this->donor('donor.standard.3@yopmail.com', 'Ngozi', 'Chukwu');

        $oneTimeAmount = round(max((float) $campaign->goal_amount * 0.06, 1000), 2);
        $this->makeOneTimePledge($campaign, $oneTimeDonor, $oneTimeAmount, $campaign->currency, "SEED-PLEDGE-STD-ONE-{$campaign->id}");

        $recurringTotal = round(max((float) $campaign->goal_amount * 0.09, 3000), 2);
        $this->makeRecurringPledge($campaign, $recurringDonor, $recurringTotal, $campaign->currency, "SEED-PLEDGE-STD-REC-{$campaign->id}");

        $campaign->forceFill([
            'raised_amount' => (string) (
                (float) DonationPayment::query()
                    ->join('donations', 'donations.id', '=', 'donation_payments.donation_id')
                    ->where('donations.campaign_id', $campaign->id)
                    ->where('donation_payments.status', PaymentStatusEnum::SUCCESSFUL->value)
                    ->sum('donation_payments.amount')
                + (float) Pledge::query()->where('campaign_id', $campaign->id)->sum('amount_paid')
            ),
        ])->save();
    }

    private function seedNationalGivingDay(Campaign $campaign): void
    {
        /** @var CampaignInstitution $campaignInstitution */
        foreach ($campaign->campaignInstitutions as $campaignInstitution) {
            $institution = $campaignInstitution->institution;

            $donor = $this->donor(
                'donor.'.Str::slug($institution->name).'@yopmail.com',
                'Alumni',
                $institution->name,
                $institution->tertiaryInstitution->name,
            );

            $amount = round(max((float) $campaignInstitution->goal_amount * 0.15, 1000), 2);

            $this->makeOneTimePledge(
                $campaign,
                $donor,
                $amount,
                $campaignInstitution->currency,
                "SEED-PLEDGE-NGD-{$campaign->id}-{$campaignInstitution->id}",
            );
        }
    }

    private function makeOneTimePledge(Campaign $campaign, User $user, float $amount, string $currency, string $reference): void
    {
        if (Pledge::query()->where('campaign_id', $campaign->id)->where('user_id', $user->id)->exists()) {
            return;
        }

        $pledge = Pledge::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'frequency' => PledgeFrequencyEnum::ONE_TIME->value,
            'currency' => $currency,
            'total_amount' => $amount,
            'amount_paid' => $amount,
            'installment_count' => 1,
            'status' => PledgeStatusEnum::COMPLETED->value,
            'is_anonymous' => false,
            'next_installment_due_at' => null,
            'completed_at' => now(),
        ]);

        $installment = PledgeInstallment::create([
            'pledge_id' => $pledge->id,
            'sequence' => 1,
            'amount' => $amount,
            'currency' => $currency,
            'due_date' => now()->toDateString(),
            'status' => PledgeInstallmentStatusEnum::PAID->value,
            'paid_at' => now(),
        ]);

        $this->makeSuccessfulPledgePayment($pledge, $installment, $user, $amount, $currency, $reference);
    }

    private function makeRecurringPledge(Campaign $campaign, User $user, float $totalAmount, string $currency, string $referencePrefix): void
    {
        if (Pledge::query()->where('campaign_id', $campaign->id)->where('user_id', $user->id)->exists()) {
            return;
        }

        $installmentCount = 3;
        $installmentAmount = round($totalAmount / $installmentCount, 2);
        $startDate = Carbon::today();

        $pledge = Pledge::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'frequency' => PledgeFrequencyEnum::MONTHLY->value,
            'currency' => $currency,
            'total_amount' => $installmentAmount * $installmentCount,
            'amount_paid' => 0,
            'installment_count' => $installmentCount,
            'status' => PledgeStatusEnum::ON_TRACK->value,
            'is_anonymous' => false,
            'start_date' => $startDate->toDateString(),
            'end_date' => $startDate->copy()->addMonths($installmentCount - 1)->toDateString(),
            'next_installment_due_at' => $startDate->toDateString(),
        ]);

        for ($sequence = 1; $sequence <= $installmentCount; $sequence++) {
            $dueDate = $startDate->copy()->addMonths($sequence - 1);
            $isFirst = $sequence === 1;

            $installment = PledgeInstallment::create([
                'pledge_id' => $pledge->id,
                'sequence' => $sequence,
                'amount' => $installmentAmount,
                'currency' => $currency,
                'due_date' => $dueDate->toDateString(),
                'status' => $isFirst ? PledgeInstallmentStatusEnum::PAID->value : PledgeInstallmentStatusEnum::PENDING->value,
                'paid_at' => $isFirst ? now() : null,
            ]);

            if ($isFirst) {
                $this->makeSuccessfulPledgePayment($pledge, $installment, $user, $installmentAmount, $currency, "{$referencePrefix}-{$sequence}");
            }
        }

        $pledge->forceFill([
            'amount_paid' => $installmentAmount,
            'next_installment_due_at' => $startDate->copy()->addMonths(1)->toDateString(),
        ])->save();
    }

    private function makeSuccessfulPledgePayment(Pledge $pledge, PledgeInstallment $installment, User $user, float $amount, string $currency, string $reference): void
    {
        if (PledgePayment::query()->where('gateway_reference', $reference)->exists()) {
            return;
        }

        PledgePayment::create([
            'pledge_id' => $pledge->id,
            'pledge_installment_id' => $installment->id,
            'user_id' => $user->id,
            'amount' => $amount,
            'currency' => $currency,
            'method' => PaymentMethodEnum::CARD->value,
            'gateway' => $currency === CurrencyEnum::NGN->value ? PaymentGatewayEnum::PAYSTACK->value : PaymentGatewayEnum::STRIPE->value,
            'gateway_reference' => $reference,
            'status' => PaymentStatusEnum::SUCCESSFUL->value,
            'card_last_four' => (string) random_int(1000, 9999),
            'paid_at' => now(),
            'meta' => ['seeded' => true],
        ]);
    }
}
