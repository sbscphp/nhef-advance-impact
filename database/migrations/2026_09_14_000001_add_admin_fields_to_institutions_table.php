<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('email')->nullable()->unique()->after('name');
            $table->string('phone_number')->nullable()->after('email');
            $table->string('country')->nullable()->after('phone_number');
            $table->string('state')->nullable()->after('country');
            $table->string('address')->nullable()->after('state');
            $table->string('status')->default('active')->index()->after('is_active');
            $table->text('invite_message')->nullable()->after('status');
            $table->uuid('created_by')->nullable()->after('invite_message');
            $table->timestamp('invited_at')->nullable()->after('created_by');
            $table->timestamp('onboarded_at')->nullable()->after('invited_at');
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn([
                'email',
                'phone_number',
                'country',
                'state',
                'address',
                'status',
                'invite_message',
                'created_by',
                'invited_at',
                'onboarded_at',
            ]);
        });
    }
};
