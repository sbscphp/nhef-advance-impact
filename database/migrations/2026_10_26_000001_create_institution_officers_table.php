<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Besides the three fixed key offices on `institutions`, NHEF can add any number of further
 * custom officers per institution, each with its own free-typed role label. Contact info only,
 * no login.
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
