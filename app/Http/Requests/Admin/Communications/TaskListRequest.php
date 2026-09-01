<?php

namespace App\Http\Requests\Admin\Communications;

use App\Enums\TaskPriorityEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class TaskListRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        ListingFilterRules::applyPeriodDateRangeToRequest($this);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(ListingFilterRules::rules(['due_date', 'created_at']), [
            'filters.priority' => ['sometimes', 'nullable', Rule::in(TaskPriorityEnum::values())],
            'filters.assigned_to' => ['sometimes', 'nullable', 'uuid', 'exists:admins,uuid'],
            'filters.view' => ['sometimes', 'nullable', Rule::in(['upcoming', 'due_today', 'overdue', 'done'])],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages(), [
            'filters.priority.in' => 'Priority filter is invalid.',
            'filters.view.in' => 'View filter is invalid.',
        ]);
    }
}
