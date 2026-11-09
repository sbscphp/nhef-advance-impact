<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_call_logs', function (Blueprint $table) {
            $table->string('log_type')->default('phone')->after('contact_user_id');
            $table->unsignedInteger('duration_minutes')->nullable()->after('priority');
            $table->string('next_action')->nullable()->after('duration_minutes');
            $table->string('other_participants')->nullable()->after('next_action');
            $table->string('phone_number', 30)->nullable()->after('other_participants');
            $table->string('email_subject')->nullable()->after('phone_number');
            $table->string('email_address')->nullable()->after('email_subject');
            $table->string('location')->nullable()->after('email_address');
        });
    }

    public function down(): void
    {
        Schema::table('communication_call_logs', function (Blueprint $table) {
            $table->dropColumn([
                'log_type',
                'duration_minutes',
                'next_action',
                'other_participants',
                'phone_number',
                'email_subject',
                'email_address',
                'location',
            ]);
        });
    }
};
