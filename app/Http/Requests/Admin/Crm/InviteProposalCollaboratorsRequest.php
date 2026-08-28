<?php

namespace App\Http\Requests\Admin\Crm;

use App\Enums\ProposalCollaboratorRoleEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class InviteProposalCollaboratorsRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'collaborators' => ['required', 'array', 'min:1'],
            'collaborators.*.admin_id' => ['required', 'uuid', 'exists:admins,uuid'],
            'collaborators.*.role' => ['required', Rule::in(ProposalCollaboratorRoleEnum::invitable())],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'collaborators.*.admin_id.exists' => 'One of the selected collaborators does not exist.',
            'collaborators.*.role.in' => 'Access type must be either edit or view.',
        ]);
    }
}
