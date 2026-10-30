<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wipes all donation/pledge/event-registration test data and resets the counters those payments
 * incremented. Not called from DatabaseSeeder; run explicitly, never against production.
 *
 * php artisan db:seed --class=CleanTestPaymentDataSeeder
 */
class CleanTestPaymentDataSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'donation_payments',
            'donations',
            'pledge_payments',
            'pledge_installments',
            'pledges',
            'event_registration_payments',
            'event_registration_items',
            'event_registrations',
            'event_waitlist_entries',
            'payment_methods',
        ] as $table) {
            DB::table($table)->truncate();
        }

        Schema::enableForeignKeyConstraints();

        DB::table('campaigns')->update(['raised_amount' => 0]);
        DB::table('events')->update(['seats_taken' => 0]);
        DB::table('event_ticket_types')->update(['quantity_sold' => 0]);
    }
}
