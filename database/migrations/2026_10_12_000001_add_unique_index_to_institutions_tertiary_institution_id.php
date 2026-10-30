<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each real-world tertiary institution must map to exactly one Institution tenant row, to avoid
 * double-counting users in per-institution breakdowns. MySQL allows multiple NULLs in a unique
 * index, so institutions with no backing TertiaryInstitution are unaffected.
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
        // The foreign key ends up relying on this unique index as its only support, so dropping
        // it directly fails (error 1553); a plain index must be added first.
        Schema::table('institutions', function (Blueprint $table) {
            $table->index('tertiary_institution_id');
        });

        Schema::table('institutions', function (Blueprint $table) {
            $table->dropUnique(['tertiary_institution_id']);
        });
    }
};
