<?php

namespace App\Http\Requests\Admin\ConstituentManagement;

use App\Enums\ModuleEnums;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * AdvanceImpact Invite Institution screen (2026-10-26): no tertiary-institution lookup - unlike
 * Nexus's universities, AdvanceImpact institutions (NGOs, charities, companies...) aren't drawn
 * from a fixed list - and a single contact person instead of three required officers. `modules`
 * is the "System Access Allowable" checklist; see AdminInstitutionService::invite().
 */
class InviteInstitutionRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:institutions,name'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:institutions,email'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'invite_message' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['required', 'string', Rule::in(ModuleEnums::institutionModuleKeys())],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'name.unique' => 'An institution with this name already exists.',
            'email.unique' => 'An institution with this email already exists.',
            'modules.required' => 'Please select at least one module to grant this institution.',
            'modules.min' => 'Please select at least one module to grant this institution.',
        ]);
    }
}
