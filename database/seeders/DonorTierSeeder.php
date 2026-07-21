<?php

namespace Database\Seeders;

use App\Models\DonorTier;
use Illuminate\Database\Seeder;

/**
 * Default donor recognition tiers (BRD REC-02). Institution-specific customization has no
 * admin CRUD yet — these are seed defaults, adjustable directly in the database until one
 * exists.
 */
class DonorTierSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            ['name' => 'Bronze Benefactor', 'minimum_amount' => 500_000, 'sort_order' => 1],
            ['name' => 'Silver Supporter', 'minimum_amount' => 1_000_000, 'sort_order' => 2],
            ['name' => 'Gold Sponsor', 'minimum_amount' => 2_500_000, 'sort_order' => 3],
            ['name' => 'Emerald Executive', 'minimum_amount' => 5_000_000, 'sort_order' => 4],
            ['name' => 'Platinum Partner', 'minimum_amount' => 10_000_000, 'sort_order' => 5],
            ['name' => 'Diamond Fellow', 'minimum_amount' => 20_000_000, 'sort_order' => 6],
        ];

        foreach ($tiers as $tier) {
            DonorTier::updateOrCreate(['name' => $tier['name']], $tier);
        }
    }
}
