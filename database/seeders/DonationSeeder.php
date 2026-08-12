<?php

namespace Database\Seeders;

use App\Enums\CampaignTypeEnum;
use App\Enums\CurrencyEnum;
use App\Enums\DonationFrequencyEnum;
use App\Enums\DonationStatusEnum;
use App\Enums\PaymentGatewayEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Campaign;
use App\Models\CampaignInstitution;
use App\Models\Donation;
use App\Models\DonationPayment;
use App\Models\User;
use Database\Seeders\Concerns\SeedsDonors;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Successful donations + payments for every seeded campaign, so admin endpoints (campaign
 * donations list, donation overview, donor breakdown, institutions tab) have real data to
 * return for local Postman testing. Depends on CampaignSeeder, InstitutionSeeder, and
 * NationalGivingDayCampaignSeeder having already run.
 *
 * National Giving Day donations are attributed to an institution purely by the donor's
 * `university` field matching the institution's name (see
 * CampaignService::listInstitutions()/nationalGivingDayTotals()), so this creates one donor
 * per institution with `university` set accordingly, rather than tying the payment to the
 * institution directly.
 *
 * php artisan db:seed --class=DonationSeeder
 */
class DonationSeeder extends Seeder
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
        $registered = $this->donor('donor.standard.1@yopmail.com', 'Amaka', 'Eze');

        $rows = [
            ['user' => $registered, 'fraction' => 0.12],
            ['user' => null, 'fraction' => 0.08],
            ['user' => null, 'fraction' => 0.05],
        ];

        foreach ($rows as $index => $row) {
            $amount = round(max((float) $campaign->goal_amount * $row['fraction'], 1000), 2);
            $this->makeSuccessfulDonation($campaign, $row['user'], $amount, $campaign->currency, "SEED-STD-{$campaign->id}-{$index}");
        }

        $campaign->forceFill([
            'raised_amount' => (string) DonationPayment::query()
                ->join('donations', 'donations.id', '=', 'donation_payments.donation_id')
                ->where('donations.campaign_id', $campaign->id)
                ->where('donation_payments.status', PaymentStatusEnum::SUCCESSFUL->value)
                ->sum('donation_payments.amount'),
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
                $institution->name,
            );

            $amount = round(max((float) $campaignInstitution->goal_amount * 0.35, 1000), 2);

            $this->makeSuccessfulDonation(
                $campaign,
                $donor,
                $amount,
                $campaignInstitution->currency,
                "SEED-NGD-{$campaign->id}-{$campaignInstitution->id}",
            );
        }
    }

    private function makeSuccessfulDonation(Campaign $campaign, ?User $user, float $amount, string $currency, string $reference): void
    {
        if (DonationPayment::query()->where('gateway_reference', $reference)->exists()) {
            return;
        }

        $donation = Donation::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user?->id,
            'guest_name' => $user === null ? 'Guest Donor' : null,
            'guest_email' => $user === null ? 'guest.donor+'.Str::random(6).'@yopmail.com' : null,
            'frequency' => DonationFrequencyEnum::ONE_TIME->value,
            'currency' => $currency,
            'amount' => $amount,
            'total_received' => $amount,
            'status' => DonationStatusEnum::COMPLETED->value,
            'is_anonymous' => false,
            'next_charge_at' => null,
            'completed_at' => now(),
        ]);

        DonationPayment::create([
            'donation_id' => $donation->id,
            'user_id' => $user?->id,
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
