<?php

namespace App\Http\Requests\Admin\Crm;

use App\Enums\CurrencyEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class CreateProspectRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'lead_source' => ['required', 'string', 'max:255'],
            'estimated_value' => ['required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'nullable', Rule::in(CurrencyEnum::values())],
            'assign_to' => ['required', 'uuid', 'exists:admins,uuid'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'assign_to.required' => 'Please select who this prospect should be assigned to.',
            'assign_to.exists' => 'The selected assignee does not exist.',
        ]);
    }
}
