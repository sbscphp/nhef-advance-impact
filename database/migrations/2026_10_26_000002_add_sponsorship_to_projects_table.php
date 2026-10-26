<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Figma "Create New Project" step 1 ("Is this a sponsored Project" toggle + "Type of
 * Sponsorship" dropdown) - no field for this existed anywhere before. `sponsorship_type` is a
 * free-typed string, not a constrained enum: only one example value ("Educational Grant") was
 * visible in the design, not a full option list, so this isn't locked down to Rule::in() yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('is_sponsored')->default(false)->after('category');
            $table->string('sponsorship_type')->nullable()->after('is_sponsored');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['is_sponsored', 'sponsorship_type']);
        });
    }
};
