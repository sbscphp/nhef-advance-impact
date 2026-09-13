<?php

namespace App\Http\Requests\Admin\Research;

use App\Http\Requests\ApiFormRequest;

class CreateResearchRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scope' => ['nullable', 'string'],
            'methodology' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:starts_at'],

            'objectives' => ['required', 'array', 'min:1'],
            'objectives.*.title' => ['required', 'string', 'max:255'],
            'objectives.*.description' => ['nullable', 'string'],
            'objectives.*.all_team_members' => ['sometimes', 'boolean'],
            'objectives.*.assignee_uuids' => ['sometimes', 'array'],
            'objectives.*.assignee_uuids.*' => ['uuid', 'exists:admins,uuid'],

            'milestones' => ['sometimes', 'array'],
            'milestones.*.title' => ['required_with:milestones', 'string', 'max:255'],
            'milestones.*.starts_at' => ['nullable', 'date'],
            'milestones.*.due_at' => ['required_with:milestones', 'date'],
            'milestones.*.completion_percentage' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'milestones.*.all_team_members' => ['sometimes', 'boolean'],
            'milestones.*.assignee_uuids' => ['sometimes', 'array'],
            'milestones.*.assignee_uuids.*' => ['uuid', 'exists:admins,uuid'],
            'milestones.*.notify_all_team_members' => ['sometimes', 'boolean'],
            'milestones.*.notify_admin_uuids' => ['sometimes', 'array'],
            'milestones.*.notify_admin_uuids.*' => ['uuid', 'exists:admins,uuid'],

            // milestone_index references a 0-based position in the milestones array above,
            // since a milestone created in this same request has no uuid yet to link against.
            'deliverables' => ['sometimes', 'array'],
            'deliverables.*.title' => ['required_with:deliverables', 'string', 'max:255'],
            'deliverables.*.starts_at' => ['nullable', 'date'],
            'deliverables.*.due_at' => ['required_with:deliverables', 'date'],
            'deliverables.*.milestone_index' => ['nullable', 'integer', 'min:0'],
            'deliverables.*.all_team_members' => ['sometimes', 'boolean'],
            'deliverables.*.assignee_uuids' => ['sometimes', 'array'],
            'deliverables.*.assignee_uuids.*' => ['uuid', 'exists:admins,uuid'],
            'deliverables.*.notify_all_team_members' => ['sometimes', 'boolean'],
            'deliverables.*.notify_admin_uuids' => ['sometimes', 'array'],
            'deliverables.*.notify_admin_uuids.*' => ['uuid', 'exists:admins,uuid'],
        ];
    }
}
