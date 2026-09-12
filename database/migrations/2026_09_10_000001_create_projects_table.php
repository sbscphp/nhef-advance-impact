<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('status')->default('draft')->index();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('country')->nullable();
            $table->string('state_lga')->nullable();
            $table->text('address')->nullable();
            $table->boolean('funded_by_donor')->default(false);
            $table->boolean('funded_by_donation')->default(false);
            $table->decimal('budget_min', 15, 2)->nullable();
            $table->decimal('budget_max', 15, 2)->nullable();
            $table->decimal('approved_budget', 15, 2)->default(0);
            $table->decimal('funding_received', 15, 2)->default(0);
            $table->string('currency')->default('NGN');
            $table->uuid('created_by')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('on_hold_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
