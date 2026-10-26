<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each real-world tertiary institution must map to exactly one Institution tenant row -
 * without this, two Institution rows can point at the same TertiaryInstitution, double-counting
 * its users in any per-institution breakdown that joins on tertiary_institution_id. MySQL allows
 * multiple NULLs in a unique index, so NHEF-created institutions without a backing
 * TertiaryInstitution are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->unique('tertiary_institution_id');
        });
    }

    public function down(): void
    {
        // The foreign key on this column (added in the earlier migration that created it via
        // foreignId()) ends up relying on THIS unique index as its only supporting index once
        // up() runs - MySQL doesn't keep the original plain index alongside it. Dropping the
        // unique index directly fails with "needed in a foreign key constraint" (error 1553), so
        // a plain index has to be added first, in its own statement, before the unique one goes.
        Schema::table('institutions', function (Blueprint $table) {
            $table->index('tertiary_institution_id');
        });

        Schema::table('institutions', function (Blueprint $table) {
            $table->dropUnique(['tertiary_institution_id']);
        });
    }
};
