<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('matric_no')->nullable()->after('phone_number');
            $table->string('department')->nullable()->after('matric_no');
            $table->unsignedSmallInteger('year_of_graduation')->nullable()->after('department');
            $table->string('degree_earned')->nullable()->after('year_of_graduation');
            $table->string('employment_status')->nullable()->after('degree_earned');
            $table->string('organisation_name')->nullable()->after('employment_status');
            $table->string('position')->nullable()->after('organisation_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'matric_no',
                'department',
                'year_of_graduation',
                'degree_earned',
                'employment_status',
                'organisation_name',
                'position',
            ]);
        });
    }
};
