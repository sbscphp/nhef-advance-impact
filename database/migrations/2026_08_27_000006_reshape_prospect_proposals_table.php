<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('prospect_proposals', 'name') && ! Schema::hasColumn('prospect_proposals', 'title')) {
            Schema::table('prospect_proposals', function (Blueprint $table) {
                $table->renameColumn('name', 'title');
            });
        }

        Schema::table('prospect_proposals', function (Blueprint $table) {
            // No explicit dropIndex here: MySQL auto-shrinks the composite index to just
            // `prospect_id` since the FK still needs a supporting index once send_status is gone.
            $table->dropColumn(['send_status', 'file_url']);

            $table->longText('body')->nullable()->after('title');
            $table->string('status')->default('draft')->index()->after('body');
            $table->json('recipient_emails')->nullable()->after('status');
            $table->string('send_message_title')->nullable()->after('recipient_emails');
            $table->text('send_message_body')->nullable()->after('send_message_title');
            $table->json('attachments')->nullable()->after('send_message_body');
            $table->uuid('sent_by')->nullable()->after('attachments')->comment('Admin uuid who sent the proposal to the client.');
            $table->timestamp('sent_at')->nullable()->after('sent_by');

            $table->index(['prospect_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('prospect_proposals', function (Blueprint $table) {
            $table->dropIndex(['prospect_id', 'status']);
            $table->dropColumn([
                'body', 'status', 'recipient_emails', 'send_message_title',
                'send_message_body', 'attachments', 'sent_by', 'sent_at',
            ]);
            $table->string('file_url')->nullable();
            $table->string('send_status')->default('pending')->index();
        });

        if (Schema::hasColumn('prospect_proposals', 'title') && ! Schema::hasColumn('prospect_proposals', 'name')) {
            Schema::table('prospect_proposals', function (Blueprint $table) {
                $table->renameColumn('title', 'name');
            });
        }
    }
};
