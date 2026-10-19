<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('phone_number')->nullable()->after('job_title');
            $table->string('country_code', 10)->nullable()->comment('prefix e.g. +234, 234')->after('phone_number');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn(['phone_number', 'country_code']);
        });
    }
};
