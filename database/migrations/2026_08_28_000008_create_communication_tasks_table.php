<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('call_log_id')->nullable()->constrained('communication_call_logs')->nullOnDelete();
            $table->foreignId('parent_task_id')->nullable()->constrained('communication_tasks')->cascadeOnDelete();
            $table->string('title');
            $table->uuid('assigned_to');
            $table->string('priority')->default('medium');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('due_date');
            $table->string('status')->default('pending');
            $table->boolean('reminders_enabled')->default(true);

            $table->boolean('reminder_2_days_before')->default(false);
            $table->timestamp('reminder_2_days_sent_at')->nullable();
            $table->boolean('reminder_1_day_before')->default(false);
            $table->timestamp('reminder_1_day_sent_at')->nullable();
            $table->boolean('reminder_on_due_date')->default(false);
            $table->timestamp('reminder_on_due_sent_at')->nullable();

            $table->boolean('is_recurring')->default(false);
            $table->date('recurrence_end_date')->nullable();
            $table->string('recurrence_status')->nullable();
            $table->boolean('repeat_non_stop')->default(false);
            $table->unsignedInteger('recurrence_interval_value')->nullable();
            $table->string('recurrence_interval_unit')->nullable();

            $table->uuid('created_by');
            $table->timestamps();

            $table->index(['status', 'due_date']);
            $table->index(['assigned_to']);
            $table->index(['is_recurring', 'recurrence_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_tasks');
    }
};
