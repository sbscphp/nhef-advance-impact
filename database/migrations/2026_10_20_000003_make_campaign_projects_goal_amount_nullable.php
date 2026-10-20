<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_projects', function (Blueprint $table) {
            $table->decimal('goal_amount', 15, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Real projects created after this migration may have a null goal_amount; backfill
        // before re-adding NOT NULL so the rollback itself doesn't fail on real data.
        \Illuminate\Support\Facades\DB::table('campaign_projects')->whereNull('goal_amount')->update(['goal_amount' => 0]);

        Schema::table('campaign_projects', function (Blueprint $table) {
            $table->decimal('goal_amount', 15, 2)->nullable(false)->change();
        });
    }
};
