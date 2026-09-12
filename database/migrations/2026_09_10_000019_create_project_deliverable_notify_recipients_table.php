<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_deliverable_notify_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliverable_id')->constrained('project_deliverables')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['deliverable_id', 'admin_id'], 'proj_deliverable_notify_recipients_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_deliverable_notify_recipients');
    }
};
