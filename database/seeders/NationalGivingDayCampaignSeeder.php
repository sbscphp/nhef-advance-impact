<?php

namespace Database\Seeders;

use App\Enums\CampaignStatusEnum;
use App\Enums\CampaignTypeEnum;
use App\Enums\CurrencyEnum;
use App\Models\Admin;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Campaign;
use App\Models\Institution;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * One sample National Giving Day campaign with three institution allocations, for local
 * development and Postman testing. Depends on AdminSeeder, BankSeeder, and InstitutionSeeder
 * having already run.
 *
 * php artisan db:seed --class=NationalGivingDayCampaignSeeder
 */
class NationalGivingDayCampaignSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Admin::query()->first();
        if (! $admin instanceof Admin) {
            $this->command?->warn('No admin found; skipping NationalGivingDayCampaignSeeder. Run AdminSeeder first.');

            return;
        }

        $institutionNames = ['University of Lagos', 'University of Ibadan', 'University of Abuja'];
        $institutions = Institution::query()->whereIn('name', $institutionNames)->get()->keyBy('name');
        if ($institutions->count() < count($institutionNames)) {
            $this->command?->warn('Institutions not found; skipping NationalGivingDayCampaignSeeder. Run InstitutionSeeder first.');

            return;
        }

        $bank = Bank::query()->active()->first();
        if (! $bank instanceof Bank) {
            $this->command?->warn('No bank found; skipping NationalGivingDayCampaignSeeder. Run BankSeeder first.');

            return;
        }

        $title = 'NHEF National Giving Day 2026';
        $campaign = Campaign::firstOrCreate(
            ['slug' => Str::slug($title)],
            [
                'title' => $title,
                'description' => 'A single day of coordinated giving across partner universities, with funds routed to each institution\'s own recipient account.',
                'type' => CampaignTypeEnum::NATIONAL_GIVING_DAY->value,
                'currency' => null,
                'goal_amount' => null,
                'raised_amount' => 0,
                'allow_one_time' => true,
                'allow_recurring' => true,
                'allow_anonymous' => true,
                'status' => CampaignStatusEnum::ACTIVE->value,
                'starts_at' => now()->subDays(3)->toDateString(),
                'ends_at' => now()->addDays(27)->toDateString(),
                'created_by' => $admin->uuid,
                'allocated_admin_id' => $admin->id,
                'bank_account_id' => null,
            ]
        );

        if ($campaign->campaignInstitutions()->exists()) {
            return;
        }

        $goals = [
            'University of Lagos' => 3_500_000,
            'University of Ibadan' => 2_800_000,
            'University of Abuja' => 2_000_000,
        ];

        foreach ($institutionNames as $index => $name) {
            $accountNumber = str_pad((string) (1000000000 + $index), 10, '0', STR_PAD_LEFT);

            $bankAccount = BankAccount::firstOrCreate(
                ['bank_id' => $bank->id, 'account_number' => $accountNumber],
                [
                    'account_name' => $name.' Giving Day Account',
                    'created_by' => $admin->uuid,
                ]
            );

            $campaign->campaignInstitutions()->create([
                'institution_id' => $institutions[$name]->id,
                'goal_amount' => $goals[$name],
                'currency' => CurrencyEnum::NGN->value,
                'bank_account_id' => $bankAccount->id,
            ]);
        }
    }
}
