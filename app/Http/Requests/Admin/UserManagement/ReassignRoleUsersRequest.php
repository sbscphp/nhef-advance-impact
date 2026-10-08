<?php

namespace App\Http\Requests\Admin\UserManagement;

use App\Http\Requests\ApiFormRequest;
use App\Models\Role;
use Illuminate\Validation\Rule;

class ReassignRoleUsersRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'reassign_to_role_id' => [
                'sometimes',
                'nullable',
                'string',
                'uuid',
                Rule::exists((new Role)->getTable(), 'uuid')->where(
                    fn ($query) => $query->where('guard_name', 'api')
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'reassign_to_role_id.uuid' => 'The role to reassign users to is invalid.',
            'reassign_to_role_id.exists' => 'The role to reassign users to does not exist.',
        ]);
    }
}
