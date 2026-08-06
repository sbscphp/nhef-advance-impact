<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Populates the "Select Bank" dropdown in the admin campaign wizard with the full,
 * CBN-recognized Nigerian bank list, fetched live from Paystack.
 *
 * php artisan db:seed --class=BankSeeder
 */
class BankSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('banks:sync');

        $this->command?->info(trim(Artisan::output()));
    }
}
