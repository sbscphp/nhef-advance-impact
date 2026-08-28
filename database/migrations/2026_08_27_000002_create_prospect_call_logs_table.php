<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_call_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('prospect_id')->constrained('prospects')->cascadeOnDelete();
            $table->uuid('logged_by')->nullable()->comment('Admin uuid who logged the call; also the contact person shown on the record.');
            $table->string('purpose');
            $table->text('description');
            $table->string('priority')->default('medium');
            $table->timestamp('call_date');
            $table->timestamps();

            $table->index(['prospect_id', 'call_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_call_logs');
    }
};
