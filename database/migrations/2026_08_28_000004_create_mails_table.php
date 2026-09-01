<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mails', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('banner_url')->nullable();
            $table->longText('body');
            $table->string('status')->default('draft');
            $table->json('segment_criteria')->nullable();
            $table->timestamp('send_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->uuid('sent_by')->nullable();
            $table->uuid('created_by');
            $table->timestamps();

            $table->index(['status', 'send_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mails');
    }
};
