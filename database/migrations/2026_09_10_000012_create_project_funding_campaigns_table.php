<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Specific campaigns (e.g. "Endowment Donation 2026") a project's funding is attributed to. */
    public function up(): void
    {
        Schema::create('project_funding_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_funding_campaigns');
    }
};
