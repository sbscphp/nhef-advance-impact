<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('send_date');
            $table->string('delivery_via');
            $table->text('message');
            $table->boolean('all_team_members')->default(true);
            $table->json('attachment_urls')->nullable();
            $table->unsignedInteger('number_of_reach')->default(0);
            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_broadcasts');
    }
};
