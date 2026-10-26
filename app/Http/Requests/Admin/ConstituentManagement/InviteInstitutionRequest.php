<?php

namespace App\Http\Requests\Admin\ConstituentManagement;

use App\Http\Requests\ApiFormRequest;

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
            // Client requirement (2026-10-23): three key offices, captured for NHEF's own
            // records. The Advancement Database Manager becomes the institution's own admin
            // login (see AdminInstitutionService::invite()); the other two are contact info only.
            'vc_name' => ['required', 'string', 'max:255'],
            'vc_email' => ['required', 'email', 'max:255'],
            'vc_phone_number' => ['required', 'string', 'max:30'],
            'advancement_director_name' => ['required', 'string', 'max:255'],
            'advancement_director_email' => ['required', 'email', 'max:255'],
            'advancement_director_phone_number' => ['required', 'string', 'max:30'],
            'advancement_database_manager_name' => ['required', 'string', 'max:255'],
            'advancement_database_manager_email' => ['required', 'email', 'max:255'],
            'advancement_database_manager_phone_number' => ['required', 'string', 'max:30'],
            // Any further custom officers beyond the three fixed ones above (2026-10-26) -
            // optional at invite time; can also be added one at a time afterward via
            // POST .../institutions/{uuid}/officers.
            'officers' => ['sometimes', 'array'],
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
            'officers.*.role.required' => 'Please enter a role for each officer.',
            'officers.*.name.required' => 'Please enter a name for each officer.',
            'officers.*.email.required' => 'Please enter an email for each officer.',
            'officers.*.phone_number.required' => 'Please enter a phone number for each officer.',
            'vc_name.required' => 'Please enter the Vice Chancellor\'s name.',
            'vc_email.required' => 'Please enter the Vice Chancellor\'s email.',
            'vc_phone_number.required' => 'Please enter the Vice Chancellor\'s phone number.',
            'advancement_director_name.required' => 'Please enter the Advancement Director\'s name.',
            'advancement_director_email.required' => 'Please enter the Advancement Director\'s email.',
            'advancement_director_phone_number.required' => 'Please enter the Advancement Director\'s phone number.',
            'advancement_database_manager_name.required' => 'Please enter the Advancement Database Manager\'s name.',
            'advancement_database_manager_email.required' => 'Please enter the Advancement Database Manager\'s email.',
            'advancement_database_manager_phone_number.required' => 'Please enter the Advancement Database Manager\'s phone number.',
        ]);
    }
}
