<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A logo belongs to the full tertiary_institutions reference list, not just the handful NHEF
 * has onboarded as a partner, so it moves off `institutions` onto `tertiary_institutions`.
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
