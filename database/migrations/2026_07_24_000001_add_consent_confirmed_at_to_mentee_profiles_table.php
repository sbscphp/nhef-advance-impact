<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentee_profiles', function (Blueprint $table) {
            $table->timestamp('consent_confirmed_at')->after('portfolio_url');
        });
    }

    public function down(): void
    {
        Schema::table('mentee_profiles', function (Blueprint $table) {
            $table->dropColumn('consent_confirmed_at');
        });
    }
};
