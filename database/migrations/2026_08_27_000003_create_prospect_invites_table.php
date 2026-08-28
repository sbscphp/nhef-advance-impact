<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_invites', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('prospect_id')->constrained('prospects')->cascadeOnDelete();
            $table->uuid('sent_by')->nullable()->comment('Admin uuid who sent the invite.');
            $table->string('title');
            $table->text('description');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('invite_type')->default('online');
            $table->string('virtual_link')->nullable();
            $table->string('venue')->nullable();
            $table->timestamps();

            $table->index(['prospect_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_invites');
    }
};
