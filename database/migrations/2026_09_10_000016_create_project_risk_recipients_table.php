<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_risk_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_id')->constrained('project_risks')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['risk_id', 'admin_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_risk_recipients');
    }
};
