<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('role_title')->nullable();
            $table->boolean('is_manager')->default(false);
            $table->timestamps();
            $table->unique(['project_id', 'admin_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_team_members');
    }
};
