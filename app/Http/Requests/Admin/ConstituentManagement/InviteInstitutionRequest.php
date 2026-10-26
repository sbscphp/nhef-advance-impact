<?php

namespace App\Http\Requests\Admin\ConstituentManagement;

use App\Enums\InstitutionRequiredOfficerRoleEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Validator;

class InviteInstitutionRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tertiary_institution_uuid' => ['required', 'uuid', 'exists:tertiary_institutions,uuid'],
            'email' => ['required', 'email', 'max:255', 'unique:institutions,email'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'invite_message' => ['sometimes', 'nullable', 'string', 'max:2000'],
            // One array for every contact (2026-10-26, FE feedback) - the three required offices
            // (see InstitutionRequiredOfficerRoleEnum) identified by `role`, same as any further
            // custom officer. The Advancement Database Manager entry becomes the institution's
            // own admin login (see AdminInstitutionService::invite()); the rest are contact info
            // only. Checked for the three required roles in withValidator() below.
            'officers' => ['required', 'array', 'min:3'],
            'officers.*.role' => ['required', 'string', 'max:255'],
            'officers.*.name' => ['required', 'string', 'max:255'],
            'officers.*.email' => ['required', 'email', 'max:255'],
            'officers.*.phone_number' => ['required', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'email.unique' => 'An institution with this email already exists.',
            'officers.required' => 'Please add the institution\'s contacts.',
            'officers.min' => 'Please include the Vice Chancellor, Advancement Director, and Advancement Database Manager.',
            'officers.*.role.required' => 'Please enter a role for each contact.',
            'officers.*.name.required' => 'Please enter a name for each contact.',
            'officers.*.email.required' => 'Please enter an email for each contact.',
            'officers.*.phone_number.required' => 'Please enter a phone number for each contact.',
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $roles = collect((array) $this->input('officers', []))->pluck('role')->all();

            foreach (InstitutionRequiredOfficerRoleEnum::cases() as $required) {
                if (! in_array($required->value, $roles, true)) {
                    $validator->errors()->add('officers', 'Please include a contact for the '.$required->label().'.');
                }
            }
        });
    }
}
