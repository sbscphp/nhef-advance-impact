<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            CountrySeeder::class,
            TertiaryInstitutionSeeder::class,
            InstitutionSeeder::class,
            AdminSeeder::class,
            CustomerSeeder::class,
            ThemeSeeder::class,
            ApiUserSeeder::class,
            BankSeeder::class,
            DonorTierSeeder::class,
            // FmeDemoDataSeeder replaces CampaignSeeder/NationalGivingDayCampaignSeeder/
            // DonationSeeder/PledgeSeeder/EventSeeder/MentorshipSeeder/NetworkingSeeder in the
            // default run: those create unlinked (no institution_id/created_by), zero-value
            // (no registrations, so seats_taken/quantity_sold stay 0), or placeholder-named
            // ("Mentor One", "Olivia Grant") records that read as broken data in the admin
            // tables. They're left as standalone classes (still runnable via
            // `db:seed --class=X`) for anyone who wants a minimal dataset instead.
            FmeDemoDataSeeder::class,
        ]);
    }
}
