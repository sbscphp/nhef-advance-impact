<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type');
            $table->json('applicable_modules');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_searchable')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->boolean('show_in_reports')->default(false);
            $table->string('status')->default('active')->index();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_definitions');
    }
};
