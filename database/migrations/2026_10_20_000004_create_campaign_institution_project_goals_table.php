<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_institution_project_goals', function (Blueprint $table) {
            $table->id();
            // Explicit, short constraint names: the default auto-generated names exceed MySQL's
            // 64-character identifier limit on this table's long name.
            $table->foreignId('campaign_institution_id')->constrained('campaign_institutions', indexName: 'cipg_campaign_institution_id_fk')->cascadeOnDelete();
            $table->foreignId('campaign_project_id')->constrained('campaign_projects', indexName: 'cipg_campaign_project_id_fk')->cascadeOnDelete();
            $table->decimal('goal_amount', 15, 2);
            $table->timestamps();

            $table->unique(['campaign_institution_id', 'campaign_project_id'], 'cipg_institution_project_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_institution_project_goals');
    }
};
