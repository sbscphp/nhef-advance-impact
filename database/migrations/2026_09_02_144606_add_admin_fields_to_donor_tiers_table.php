<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('donor_tiers', function (Blueprint $table) {
            $table->decimal('maximum_amount', 15, 2)->nullable()->after('minimum_amount');
            $table->string('badge_url')->nullable()->after('maximum_amount');
            $table->boolean('is_active')->default(true)->after('badge_url');
            $table->string('created_by')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donor_tiers', function (Blueprint $table) {
            $table->dropColumn(['maximum_amount', 'badge_url', 'is_active', 'created_by']);
        });
    }
};
