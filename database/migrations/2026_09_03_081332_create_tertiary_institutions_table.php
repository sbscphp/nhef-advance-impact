<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tertiary_institutions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->unique();
            $table->string('type')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('abbreviation')->nullable();
            $table->string('website')->nullable();
            // False for anything added on the fly when an alumnus's school wasn't in the seeded
            // list (see TertiaryInstitutionRepository::findOrCreateByName()); an admin can merge
            // near-duplicates or fill in missing type/state later.
            $table->boolean('is_verified')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tertiary_institutions');
    }
};
