<?php

namespace App\Http\Requests\Admin\Crm;

use App\Enums\ProspectCallPriorityEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class LogProspectCallRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'call_purpose' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['sometimes', 'nullable', Rule::in(ProspectCallPriorityEnum::values())],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'priority.in' => 'Priority must be one of: low, medium, high, critical.',
        ]);
    }
}
