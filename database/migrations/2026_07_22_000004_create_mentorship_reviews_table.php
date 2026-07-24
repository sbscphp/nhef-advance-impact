<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentorship_reviews', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('mentorship_match_id')->unique()->constrained('mentorship_matches')->cascadeOnDelete();
            $table->unsignedTinyInteger('quality_rating');
            $table->unsignedTinyInteger('communication_rating');
            $table->unsignedTinyInteger('responsiveness_rating');
            $table->unsignedTinyInteger('professionalism_rating');
            $table->text('description');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentorship_reviews');
    }
};
