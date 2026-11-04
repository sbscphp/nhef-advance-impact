<?php

namespace App\Http\Requests\Admin\Communications;

use App\Enums\CommunicationCallPriorityEnum;
use App\Enums\CommunicationLogTypeEnum;
use App\Enums\TaskPriorityEnum;
use App\Enums\TaskRecurrenceIntervalUnitEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class LogCallRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'contact_user_uuid' => ['required', 'uuid', 'exists:users,uuid'],
            'log_type' => ['required', Rule::in(CommunicationLogTypeEnum::values())],
            'occurred_at' => ['sometimes', 'nullable', 'date'],
            'summary' => ['required', 'string'],
            'priority' => ['sometimes', 'nullable', Rule::in(CommunicationCallPriorityEnum::values())],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'next_action' => ['sometimes', 'nullable', 'string', 'max:255'],
            'other_participants' => ['sometimes', 'nullable', 'string', 'max:255'],

            // Type-specific; each is only meaningful (and only accepted) for its own log_type.
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:30', 'prohibited_unless:log_type,phone'],
            'email_subject' => ['sometimes', 'nullable', 'string', 'max:255', 'prohibited_unless:log_type,email'],
            'email_address' => ['sometimes', 'nullable', 'email', 'max:255', 'prohibited_unless:log_type,email'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255', 'prohibited_unless:log_type,visit'],

            // Any number of follow-up tasks can be added in the same call log.
            'follow_up_tasks' => ['sometimes', 'nullable', 'array'],
            'follow_up_tasks.*.title' => ['required', 'string', 'max:255'],
            'follow_up_tasks.*.assigned_to' => ['required', 'uuid', 'exists:admins,uuid'],
            'follow_up_tasks.*.priority' => ['required', Rule::in(TaskPriorityEnum::values())],
            'follow_up_tasks.*.description' => ['sometimes', 'nullable', 'string'],
            'follow_up_tasks.*.start_date' => ['required', 'date'],
            'follow_up_tasks.*.due_date' => ['required', 'date', 'after_or_equal:follow_up_tasks.*.start_date'],
            // Single master toggle here, no per-window picks; defaults every window on.
            'follow_up_tasks.*.reminders_enabled' => ['sometimes', 'boolean'],
            'follow_up_tasks.*.is_recurring' => ['sometimes', 'boolean'],
            'follow_up_tasks.*.repeat_non_stop' => ['sometimes', 'boolean'],
            'follow_up_tasks.*.recurrence_interval_value' => ['required_if:follow_up_tasks.*.is_recurring,true', 'nullable', 'integer', 'min:1'],
            'follow_up_tasks.*.recurrence_interval_unit' => ['required_if:follow_up_tasks.*.is_recurring,true', 'nullable', Rule::in(TaskRecurrenceIntervalUnitEnum::values())],
            // Not required when "Repeat Non Stop" is on for that same entry: the closure checks
            // both sibling fields because required_if/required_unless can't be ANDed together.
            'follow_up_tasks.*.recurrence_end_date' => [
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $index = explode('.', $attribute)[1];
                    $isRecurring = (bool) data_get($this->all(), "follow_up_tasks.{$index}.is_recurring", false);
                    $repeatNonStop = (bool) data_get($this->all(), "follow_up_tasks.{$index}.repeat_non_stop", false);

                    if ($isRecurring && ! $repeatNonStop && blank($value)) {
                        $fail('The recurrence end date is required unless repeat non stop is enabled.');
                    }
                },
                'nullable', 'date', 'after:follow_up_tasks.*.due_date',
            ],
        ];
    }
}
