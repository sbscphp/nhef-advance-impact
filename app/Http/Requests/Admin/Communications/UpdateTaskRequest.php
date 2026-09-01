<?php

namespace App\Http\Requests\Admin\Communications;

use App\Enums\TaskPriorityEnum;
use App\Enums\TaskRecurrenceIntervalUnitEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'assigned_to' => ['sometimes', 'uuid', 'exists:admins,uuid'],
            'priority' => ['sometimes', Rule::in(TaskPriorityEnum::values())],
            'description' => ['sometimes', 'nullable', 'string'],
            'start_date' => ['sometimes', 'date'],
            'due_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'reminders_enabled' => ['sometimes', 'boolean'],
            'reminder_2_days_before' => ['sometimes', 'boolean'],
            'reminder_1_day_before' => ['sometimes', 'boolean'],
            'reminder_on_due_date' => ['sometimes', 'boolean'],
            'is_recurring' => ['sometimes', 'boolean'],
            'repeat_non_stop' => ['sometimes', 'boolean'],
            'recurrence_interval_value' => [Rule::requiredIf(fn () => $this->has('is_recurring') && $this->boolean('is_recurring')), 'nullable', 'integer', 'min:1'],
            'recurrence_interval_unit' => [Rule::requiredIf(fn () => $this->has('is_recurring') && $this->boolean('is_recurring')), 'nullable', Rule::in(TaskRecurrenceIntervalUnitEnum::values())],
            // Not required when "Repeat Non Stop" is on: that means the task recurs forever.
            'recurrence_end_date' => [Rule::requiredIf(fn () => $this->has('is_recurring') && $this->boolean('is_recurring') && ! $this->boolean('repeat_non_stop')), 'nullable', 'date', 'after:due_date'],
        ];
    }
}
