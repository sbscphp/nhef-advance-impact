<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_welcome_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('institution_id')->nullable()->unique()->constrained('institutions')->cascadeOnDelete();
            $table->string('heading')->nullable();
            $table->text('body')->nullable();
            $table->string('banner_url')->nullable();
            $table->string('photo_url')->nullable();
            $table->string('signature_url')->nullable();
            $table->string('signer_name')->nullable();
            $table->string('signer_title')->nullable();
            $table->string('tagline')->nullable();
            $table->string('tagline_emphasis')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_welcome_messages');
    }
};
