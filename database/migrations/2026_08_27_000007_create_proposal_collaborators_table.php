<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_collaborators', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('proposal_id')->constrained('prospect_proposals')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('role')->default('viewer');
            $table->uuid('invited_by')->nullable()->comment('Admin uuid who sent the invite.');
            $table->timestamp('invited_at')->nullable();
            $table->timestamps();

            $table->unique(['proposal_id', 'admin_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_collaborators');
    }
};
