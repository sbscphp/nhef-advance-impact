<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AdvanceImpact "System Access Allowable" (Invite Institution screen): which modules an
 * institution's own admins get access to, picked by Super Admin at invite time and editable
 * afterward. `module_key` stores a ModuleEnums value rather than a dedicated enum, so this stays
 * the single source of truth for module keys across both permission grouping and institution
 * access.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_modules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('module_key');
            $table->timestamps();

            $table->unique(['institution_id', 'module_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_modules');
    }
};
