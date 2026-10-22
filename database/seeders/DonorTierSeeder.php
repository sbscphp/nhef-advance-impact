<?php

namespace Database\Seeders;

use App\Models\DonorTier;
use App\Models\Institution;
use Illuminate\Database\Seeder;

/**
 * Default donor recognition tiers (BRD REC-02), institution-scoped (2026-10-22): every onboarded
 * institution gets its own copy of this same ladder (same names/thresholds/colors to start,
 * independently editable afterwards via the admin CRUD), plus one platform-wide default set
 * (institution_id null) used as a fallback for a donor whose own institution hasn't configured
 * any tiers yet.
 */
class DonorTierSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = $this->tierDefinitions();

        $this->seedForInstitution(null, $tiers);

        foreach (Institution::withoutGlobalScopes()->get() as $institution) {
            $this->seedForInstitution($institution->id, $tiers);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tierDefinitions(): array
    {
        $tiers = [
            ['name' => 'Bronze Benefactor', 'minimum_amount' => 500_000, 'color' => '#CD7F32', 'sort_order' => 1],
            ['name' => 'Silver Supporter', 'minimum_amount' => 1_000_000, 'color' => '#C0C0C0', 'sort_order' => 2],
            ['name' => 'Gold Sponsor', 'minimum_amount' => 2_500_000, 'color' => '#FFD700', 'sort_order' => 3],
            ['name' => 'Emerald Executive', 'minimum_amount' => 5_000_000, 'color' => '#50C878', 'sort_order' => 4],
            ['name' => 'Platinum Partner', 'minimum_amount' => 10_000_000, 'color' => '#A8A9AD', 'sort_order' => 5],
            ['name' => 'Diamond Fellow', 'minimum_amount' => 20_000_000, 'color' => '#B9F2FF', 'sort_order' => 6],
        ];

        // maximum_amount is display-only (see App\Models\DonorTier docblock); each tier's max is
        // simply the next tier's minimum, with the top tier left open-ended (null), matching the
        // bracket logic already used for tier resolution and the "Alumni List" tab.
        foreach ($tiers as $index => $tier) {
            $tiers[$index]['maximum_amount'] = $tiers[$index + 1]['minimum_amount'] ?? null;
            $tiers[$index]['is_active'] = true;
        }

        return $tiers;
    }

    /**
     * @param  list<array<string, mixed>>  $tiers
     */
    private function seedForInstitution(?int $institutionId, array $tiers): void
    {
        foreach ($tiers as $tier) {
            DonorTier::updateOrCreate(
                ['name' => $tier['name'], 'institution_id' => $institutionId],
                [...$tier, 'institution_id' => $institutionId]
            );
        }
    }
}
