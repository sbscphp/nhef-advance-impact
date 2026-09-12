<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_budget_line_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_line_id')->constrained('project_budget_lines')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['budget_line_id', 'admin_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_budget_line_recipients');
    }
};
