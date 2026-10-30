<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "yearly opening" a Super Admin announces once: a shared day, no money or institutions yet.
 * Institutions create their own campaigns under one of these, inheriting its starts_at/ends_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('national_giving_day_openings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->string('cover_media_type')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('status')->default('active')->index();
            $table->uuid('allocated_admin_id')->nullable()->comment('Admin uuid this opening is assigned to.');
            $table->uuid('created_by')->nullable()->comment('Admin uuid who announced this opening.');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('national_giving_day_openings');
    }
};
