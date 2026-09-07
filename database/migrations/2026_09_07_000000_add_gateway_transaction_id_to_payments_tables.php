<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the gateway's own object id (Stripe PaymentIntent/Checkout Session id) captured at
 * initialize() time, so verify() can retrieve() it directly instead of relying on Stripe's
 * Search API, which is only eventually consistent and was the cause of intermittent
 * "Unable to verify payment with the gateway" failures. Nullable: legacy pending payments
 * created before this migration have no stored id and fall back to the old search path.
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
