<?php

namespace App\Http\Requests\Admin\ConstituentManagement;

use App\Http\Requests\ApiFormRequest;

class AddInstitutionOfficerRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone_number' => ['required', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'role.required' => 'Please enter this officer\'s role.',
            'name.required' => 'Please enter this officer\'s name.',
            'email.required' => 'Please enter this officer\'s email.',
            'phone_number.required' => 'Please enter this officer\'s phone number.',
        ]);
    }
}
