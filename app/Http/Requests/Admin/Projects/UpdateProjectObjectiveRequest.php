<?php

namespace App\Http\Requests\Admin\Projects;

use App\Http\Requests\ApiFormRequest;

class UpdateProjectObjectiveRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'all_team_members' => ['sometimes', 'boolean'],
            'is_completed' => ['sometimes', 'boolean'],
            'assignee_uuids' => ['sometimes', 'array'],
            'assignee_uuids.*' => ['uuid', 'exists:admins,uuid'],
        ];
    }
}
