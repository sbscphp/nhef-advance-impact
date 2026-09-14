<?php

namespace App\Http\Requests\Admin\ConstituentManagement;

use App\Enums\InstitutionStatusEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateInstitutionRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $institutionUuid = $this->route('uuid');

        return [
            'tertiary_institution_uuid' => ['sometimes', 'uuid', 'exists:tertiary_institutions,uuid'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('institutions', 'email')->ignore($institutionUuid, 'uuid')],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'state' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'invite_message' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::in(InstitutionStatusEnum::values())],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'email.unique' => 'An institution with this email already exists.',
        ]);
    }
}
