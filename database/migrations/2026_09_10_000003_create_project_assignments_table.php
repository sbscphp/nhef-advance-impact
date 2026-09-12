<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Assigned to" for objectives, milestones, and deliverables; one shared polymorphic
     * pivot instead of three near-identical tables since the shape is always the same:
     * a project team member assigned to one of these sub-records.
     */
    public function up(): void
    {
        Schema::create('project_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('assignable_type');
            $table->unsignedBigInteger('assignable_id');
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['assignable_type', 'assignable_id']);
            $table->unique(['assignable_type', 'assignable_id', 'admin_id'], 'project_assignments_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_assignments');
    }
};
