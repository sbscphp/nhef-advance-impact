<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_proposals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('prospect_id')->constrained('prospects')->cascadeOnDelete();
            $table->string('name');
            $table->uuid('created_by')->nullable()->comment('Admin uuid who created the proposal.');
            $table->string('file_url')->nullable();
            $table->string('send_status')->default('pending')->index();
            $table->timestamps();

            $table->index(['prospect_id', 'send_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_proposals');
    }
};
