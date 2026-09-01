<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_call_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('contact_user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('logged_by');
            $table->string('purpose');
            $table->text('description');
            $table->string('priority')->default('medium');
            $table->timestamp('call_date');
            $table->timestamps();

            $table->index(['contact_user_id', 'call_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_call_logs');
    }
};
