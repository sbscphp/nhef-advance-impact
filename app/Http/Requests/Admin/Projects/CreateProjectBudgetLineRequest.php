<?php

namespace App\Http\Requests\Admin\Projects;

use App\Http\Requests\ApiFormRequest;

class CreateProjectBudgetLineRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'reference_id' => ['nullable', 'string', 'max:255'],
            'amount_allocated' => ['required', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'all_team_members' => ['sometimes', 'boolean'],
            'recipient_admin_uuids' => ['sometimes', 'array'],
            'recipient_admin_uuids.*' => ['uuid', 'exists:admins,uuid'],
        ];
    }
}
