<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('custom_field_definition_id')->constrained('custom_field_definitions')->restrictOnDelete();
            $table->morphs('fieldable');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(
                ['custom_field_definition_id', 'fieldable_type', 'fieldable_id'],
                'custom_field_values_definition_fieldable_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
    }
};
