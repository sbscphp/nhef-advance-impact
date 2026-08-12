<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('type')->default('standard')->after('slug')->index();
        });

        // A national_giving_day campaign has no single top-level goal/currency/account;
        // each targeted institution carries its own via `campaign_institutions`.
        Schema::table('campaigns', function (Blueprint $table) {
            $table->decimal('goal_amount', 15, 2)->nullable()->change();
            $table->string('currency', 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->decimal('goal_amount', 15, 2)->nullable(false)->change();
            $table->string('currency', 3)->nullable(false)->change();
        });
    }
};
