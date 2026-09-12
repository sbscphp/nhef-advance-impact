<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_milestone_notify_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milestone_id')->constrained('project_milestones')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['milestone_id', 'admin_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_milestone_notify_recipients');
    }
};
