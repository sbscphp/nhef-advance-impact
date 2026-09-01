<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_task_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('task_id')->constrained('communication_tasks')->cascadeOnDelete();
            $table->uuid('author_id');
            $table->text('body');
            $table->timestamps();

            $table->index(['task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_task_notes');
    }
};
