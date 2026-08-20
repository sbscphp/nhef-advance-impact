<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('active')->index()->after('is_active');
            $table->text('invite_message')->nullable()->after('status');
            $table->uuid('created_by')->nullable()->after('invite_message');
            $table->timestamp('invited_at')->nullable()->after('created_by');
            $table->timestamp('onboarded_at')->nullable()->after('invited_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status', 'invite_message', 'created_by', 'invited_at', 'onboarded_at']);
        });
    }
};
