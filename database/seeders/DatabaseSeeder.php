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
            ConstituentTypeDemoSeeder::class,
            ThemeSeeder::class,
            ApiUserSeeder::class,
            BankSeeder::class,
            DonorTierSeeder::class,
            // FmeDemoDataSeeder replaces the standalone Campaign/Donation/Pledge/Event/
            // Mentorship/Networking seeders in the default run, whose unlinked/zero-value/
            // placeholder-named records read as broken data in the admin tables. Those stay
            // runnable standalone (`db:seed --class=X`) for a minimal dataset.
            FmeDemoDataSeeder::class,
        ]);
    }
}
