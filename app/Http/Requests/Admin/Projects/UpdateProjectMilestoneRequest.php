<?php

namespace App\Http\Requests\Admin\Projects;

use App\Http\Requests\ApiFormRequest;

class UpdateProjectMilestoneRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'due_at' => ['sometimes', 'date'],
            'completion_percentage' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'all_team_members' => ['sometimes', 'boolean'],
            'assignee_uuids' => ['sometimes', 'array'],
            'assignee_uuids.*' => ['uuid', 'exists:admins,uuid'],
            'notify_all_team_members' => ['sometimes', 'boolean'],
            'notify_admin_uuids' => ['sometimes', 'array'],
            'notify_admin_uuids.*' => ['uuid', 'exists:admins,uuid'],
        ];
    }
}
