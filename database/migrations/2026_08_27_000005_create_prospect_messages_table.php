<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('prospect_id')->constrained('prospects')->cascadeOnDelete();
            $table->uuid('sent_by')->nullable()->comment('Admin uuid who composed the message.');
            $table->string('subject');
            $table->longText('body');
            $table->string('banner_url')->nullable();
            $table->dateTime('send_at');
            $table->string('status')->default('draft')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['prospect_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_messages');
    }
};
