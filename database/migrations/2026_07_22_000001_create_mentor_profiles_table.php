<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentor_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('current_job_title');
            $table->string('company');
            $table->unsignedSmallInteger('years_of_experience');
            $table->text('professional_summary');
            $table->text('major_achievements')->nullable();
            $table->json('expertise_tags');
            $table->json('guidance_areas');
            $table->unsignedInteger('max_capacity');
            $table->unsignedInteger('current_mentee_count')->default(0);
            $table->json('available_days');
            $table->string('frequency_of_interaction');
            $table->string('program_commitment');
            $table->string('linkedin_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('portfolio_url')->nullable();
            $table->timestamp('eligibility_confirmed_at');
            $table->string('review_status')->default('pending')->index();
            $table->string('listing_status')->default('active')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_profiles');
    }
};
