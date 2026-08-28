<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('lead_source');
            $table->decimal('estimated_value', 15, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->string('stage')->default('identification')->index();
            $table->timestamp('stage_entered_at');
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->uuid('created_by')->nullable()->comment('Admin uuid who created the prospect.');
            $table->timestamps();

            $table->index(['stage', 'assigned_admin_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};
