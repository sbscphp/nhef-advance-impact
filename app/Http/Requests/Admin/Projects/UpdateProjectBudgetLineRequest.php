<?php

namespace App\Http\Requests\Admin\Projects;

use App\Http\Requests\ApiFormRequest;

class UpdateProjectBudgetLineRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'amount_allocated' => ['sometimes', 'numeric', 'min:0'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'due_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'all_team_members' => ['sometimes', 'boolean'],
            'recipient_admin_uuids' => ['sometimes', 'array'],
            'recipient_admin_uuids.*' => ['uuid', 'exists:admins,uuid'],
        ];
    }
}
