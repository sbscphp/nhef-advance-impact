<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('firstname')->nullable()->change();
            $table->string('lastname')->nullable()->change();

            // Non-Alumni
            $table->string('gender')->nullable()->after('position');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->foreignId('country_of_residence_id')->nullable()->after('date_of_birth')
                ->constrained('countries')->nullOnDelete();
            $table->string('sector_of_employment')->nullable()->after('country_of_residence_id');
            $table->json('area_of_interest')->nullable()->after('sector_of_employment');

            // Shared by Non-Alumni and Organisation
            $table->string('address')->nullable()->after('area_of_interest');

            // Organisation
            $table->string('organisation_type')->nullable()->after('address');
            $table->text('organisation_description')->nullable()->after('organisation_type');
            $table->string('rc_number')->nullable()->after('organisation_description');
            $table->date('date_of_incorporation')->nullable()->after('rc_number');
            $table->string('website')->nullable()->after('date_of_incorporation');
            $table->foreignId('country_of_operation_id')->nullable()->after('website')
                ->constrained('countries')->nullOnDelete();
            $table->string('organisation_size')->nullable()->after('country_of_operation_id');
            $table->string('sector_of_operation')->nullable()->after('organisation_size');
            $table->json('engagement_preference')->nullable()->after('sector_of_operation');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('country_of_residence_id');
            $table->dropConstrainedForeignId('country_of_operation_id');
            $table->dropColumn([
                'gender', 'date_of_birth', 'sector_of_employment', 'area_of_interest', 'address',
                'organisation_type', 'organisation_description', 'rc_number', 'date_of_incorporation',
                'website', 'organisation_size', 'sector_of_operation', 'engagement_preference',
            ]);
            $table->string('firstname')->nullable(false)->change();
            $table->string('lastname')->nullable(false)->change();
        });
    }
};
