<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentorship_matches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('mentor_profile_id')->constrained('mentor_profiles')->restrictOnDelete();
            $table->foreignId('mentee_profile_id')->constrained('mentee_profiles')->restrictOnDelete();
            $table->string('status')->default('active')->index();
            $table->string('matched_by')->default('system');
            $table->timestamp('matched_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['mentee_profile_id', 'status']);
            $table->index(['mentor_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentorship_matches');
    }
};
