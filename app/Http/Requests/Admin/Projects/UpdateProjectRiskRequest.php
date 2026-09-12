<?php

namespace App\Http\Requests\Admin\Projects;

use App\Enums\ProjectRiskSeverityEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRiskRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'severity' => ['sometimes', Rule::in(ProjectRiskSeverityEnum::values())],
            'milestone_uuid' => ['sometimes', 'nullable', 'uuid', 'exists:project_milestones,uuid'],
            'all_team_members' => ['sometimes', 'boolean'],
            'recipient_admin_uuids' => ['sometimes', 'required_if:all_team_members,false', 'array'],
            'recipient_admin_uuids.*' => ['uuid', 'exists:admins,uuid'],
        ];
    }
}
