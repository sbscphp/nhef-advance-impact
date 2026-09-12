<?php

namespace App\Http\Requests\Admin\Projects;

use App\Enums\ProjectRiskSeverityEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class AddProjectRiskRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'severity' => ['required', Rule::in(ProjectRiskSeverityEnum::values())],
            'milestone_uuid' => ['nullable', 'uuid', 'exists:project_milestones,uuid'],
            'all_team_members' => ['sometimes', 'boolean'],
            'recipient_admin_uuids' => ['required_if:all_team_members,false', 'array'],
            'recipient_admin_uuids.*' => ['uuid', 'exists:admins,uuid'],
        ];
    }
}
