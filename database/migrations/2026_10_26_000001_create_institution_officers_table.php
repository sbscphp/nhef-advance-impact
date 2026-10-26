<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client requirement (2026-10-26): besides the three fixed key offices on `institutions` itself
 * (VC, Advancement Director, Advancement Database Manager), NHEF can now add any number of
 * further custom officers per institution - each with its own free-typed role label (e.g.
 * "Registrar", "IT Director"), not a fixed enum, since the FE defines the role per entry rather
 * than picking from a preset list. Contact info only, same as the three fixed ones - no login.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_officers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->string('name');
            $table->string('email');
            $table->string('phone_number');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_officers');
    }
};
