<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donor_tiers', function (Blueprint $table) {
            // Nullable, not a hard institution-scoping requirement: a null-institution tier is a
            // platform-wide default, used as a fallback when a donor's own institution has no
            // tiers of its own configured yet (client feedback, 2026-10-22: tiers are now
            // institution-scoped, each institution customizes its own ladder).
            $table->foreignId('institution_id')->nullable()->after('id')->constrained('institutions')->cascadeOnDelete();
            $table->string('color', 7)->nullable()->after('badge_url');
        });
    }

    public function down(): void
    {
        Schema::table('donor_tiers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('institution_id');
            $table->dropColumn('color');
        });
    }
};
