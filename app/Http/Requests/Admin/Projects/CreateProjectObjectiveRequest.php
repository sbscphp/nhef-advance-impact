<?php

namespace App\Http\Requests\Admin\Projects;

use App\Http\Requests\ApiFormRequest;

class CreateProjectObjectiveRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'all_team_members' => ['sometimes', 'boolean'],
            'assignee_uuids' => ['sometimes', 'array'],
            'assignee_uuids.*' => ['uuid', 'exists:admins,uuid'],
        ];
    }
}
