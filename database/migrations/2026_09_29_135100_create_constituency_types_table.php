<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('constituency_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable()->comment('Admin uuid who created this type.');
            $table->timestamps();
        });

        Schema::create('constituency_type_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('constituency_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('conferred_by')->nullable()->comment('Admin uuid who conferred this type on the user.');
            $table->timestamp('conferred_at')->nullable();
            $table->timestamps();
            $table->unique(['constituency_type_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('constituency_type_user');
        Schema::dropIfExists('constituency_types');
    }
};
