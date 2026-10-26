<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A logo belongs to the institution itself (the tertiary_institutions reference list, browsed in
 * full on the public signup picker and the "Join Your University Community" directory), not to
 * the handful of institutions NHEF has onboarded as a partner - so it moves off `institutions`
 * (never actually populated by any feature, no write path ever built for it) onto
 * `tertiary_institutions`. An onboarded Institution now resolves its logo through its
 * `tertiaryInstitution` relation instead of carrying its own copy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tertiary_institutions', function (Blueprint $table) {
            $table->string('logo_file_path')->nullable()->after('website');
        });

        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn('logo_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('logo_file_path')->nullable()->after('theme_id');
        });

        Schema::table('tertiary_institutions', function (Blueprint $table) {
            $table->dropColumn('logo_file_path');
        });
    }
};
