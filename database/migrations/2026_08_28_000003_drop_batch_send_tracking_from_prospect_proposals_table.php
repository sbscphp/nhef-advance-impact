<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Delivery is now tracked per recipient (see proposal_recipients), not one status for the whole send. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospect_proposals', function (Blueprint $table) {
            $table->dropColumn(['recipient_emails', 'send_attempts', 'last_attempted_at', 'last_send_error']);
        });
    }

    public function down(): void
    {
        Schema::table('prospect_proposals', function (Blueprint $table) {
            $table->json('recipient_emails')->nullable()->after('status');
            $table->unsignedInteger('send_attempts')->default(0)->after('sent_at');
            $table->timestamp('last_attempted_at')->nullable()->after('send_attempts');
            $table->text('last_send_error')->nullable()->after('last_attempted_at');
        });
    }
};
