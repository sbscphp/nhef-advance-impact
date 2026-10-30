<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the gateway's own object id, captured at initialize() time, so verify() can
 * retrieve() it directly instead of relying on Stripe's only-eventually-consistent Search API.
 * Nullable: legacy pending payments fall back to the old search path.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['donation_payments', 'pledge_payments', 'event_registration_payments'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('gateway_transaction_id')->nullable()->after('gateway_reference');
            });
        }
    }

    public function down(): void
    {
        foreach (['donation_payments', 'pledge_payments', 'event_registration_payments'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('gateway_transaction_id');
            });
        }
    }
};
