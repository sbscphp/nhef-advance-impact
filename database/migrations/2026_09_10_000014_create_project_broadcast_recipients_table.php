<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained('project_broadcasts')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['broadcast_id', 'admin_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_broadcast_recipients');
    }
};
