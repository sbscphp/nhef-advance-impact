<?php

namespace App\Http\Requests\Admin\Crm;

use App\Http\Requests\ApiFormRequest;

class UpdateProspectRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'lead_source' => ['sometimes', 'string', 'max:255'],
            'estimated_value' => ['sometimes', 'numeric', 'min:0'],
            'assign_to' => ['sometimes', 'uuid', 'exists:admins,uuid'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'assign_to.exists' => 'The selected assignee does not exist.',
        ]);
    }
}
