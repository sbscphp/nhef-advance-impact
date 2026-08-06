<?php

namespace Database\Seeders;

use App\Enums\CampaignStatusEnum;
use App\Enums\CurrencyEnum;
use App\Models\Campaign;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Sample active campaigns for local development and Postman testing.
 *
 * php artisan db:seed --class=CampaignSeeder
 */
class CampaignSeeder extends Seeder
{
    public function run(): void
    {
        $campaigns = [
            [
                'title' => 'Stellar Tech Coding Scholarship',
                'description' => 'Fund coding bootcamp scholarships for underserved alumni and their dependents.',
                'currency' => CurrencyEnum::NGN->value,
                'goal_amount' => 5000000,
                'raised_amount' => 1500000,
            ],
            [
                'title' => 'National Giving Day',
                'description' => 'Our annual day of giving supporting every active fund across the network.',
                'currency' => CurrencyEnum::NGN->value,
                'goal_amount' => 50000000,
                'raised_amount' => 12500000,
            ],
            [
                'title' => 'Diaspora Research Endowment',
                'description' => 'A permanent endowment funding faculty research grants.',
                'currency' => CurrencyEnum::USD->value,
                'goal_amount' => 100000,
                'raised_amount' => 22000,
            ],
            [
                'title' => 'Campus Library Infrastructure Fund',
                'description' => 'Renovate and re-equip the main library across partner universities.',
                'currency' => CurrencyEnum::NGN->value,
                'goal_amount' => 20000000,
                'raised_amount' => 3000000,
            ],
        ];

        foreach ($campaigns as $row) {
            Campaign::firstOrCreate(
                ['slug' => Str::slug($row['title'])],
                [
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'currency' => $row['currency'],
                    'goal_amount' => $row['goal_amount'],
                    'raised_amount' => $row['raised_amount'],
                    'allow_one_time' => true,
                    'allow_recurring' => true,
                    'allow_anonymous' => true,
                    'status' => CampaignStatusEnum::ACTIVE->value,
                    'starts_at' => now()->subMonth()->toDateString(),
                    'ends_at' => now()->addMonths(6)->toDateString(),
                ]
            );
        }
    }
}
