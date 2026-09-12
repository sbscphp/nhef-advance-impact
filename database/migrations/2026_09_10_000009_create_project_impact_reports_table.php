<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_impact_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deliverable_id')->nullable()->constrained('project_deliverables')->nullOnDelete();
            $table->foreignId('budget_line_id')->nullable()->constrained('project_budget_lines')->nullOnDelete();
            $table->string('title');
            $table->date('report_date');
            $table->text('description');
            $table->decimal('expenditure_value', 15, 2)->nullable();
            $table->json('evidence_urls')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_impact_reports');
    }
};
