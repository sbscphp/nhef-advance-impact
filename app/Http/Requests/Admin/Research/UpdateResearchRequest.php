<?php

namespace App\Http\Requests\Admin\Research;

use App\Http\Requests\ApiFormRequest;

class UpdateResearchRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'scope' => ['sometimes', 'nullable', 'string'],
            'methodology' => ['sometimes', 'nullable', 'string'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'due_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],

            // Replace-set sync: any existing objective/milestone/deliverable not included here
            // (by uuid) is deleted. Omit uuid to create a new one.
            'objectives' => ['sometimes', 'array'],
            'objectives.*.uuid' => ['nullable', 'uuid', 'exists:research_objectives,uuid'],
            'objectives.*.title' => ['required', 'string', 'max:255'],
            'objectives.*.description' => ['nullable', 'string'],
            'objectives.*.all_team_members' => ['sometimes', 'boolean'],
            'objectives.*.assignee_uuids' => ['sometimes', 'array'],
            'objectives.*.assignee_uuids.*' => ['uuid', 'exists:admins,uuid'],

            'milestones' => ['sometimes', 'array'],
            'milestones.*.uuid' => ['nullable', 'uuid', 'exists:research_milestones,uuid'],
            'milestones.*.title' => ['required', 'string', 'max:255'],
            'milestones.*.starts_at' => ['nullable', 'date'],
            'milestones.*.due_at' => ['required', 'date'],
            'milestones.*.completion_percentage' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'milestones.*.all_team_members' => ['sometimes', 'boolean'],
            'milestones.*.assignee_uuids' => ['sometimes', 'array'],
            'milestones.*.assignee_uuids.*' => ['uuid', 'exists:admins,uuid'],
            'milestones.*.notify_all_team_members' => ['sometimes', 'boolean'],
            'milestones.*.notify_admin_uuids' => ['sometimes', 'array'],
            'milestones.*.notify_admin_uuids.*' => ['uuid', 'exists:admins,uuid'],

            // milestone_index references a position in the milestones array above, for a
            // deliverable linked to a milestone created in this same request (no uuid yet).
            'deliverables' => ['sometimes', 'array'],
            'deliverables.*.uuid' => ['nullable', 'uuid', 'exists:research_deliverables,uuid'],
            'deliverables.*.title' => ['required', 'string', 'max:255'],
            'deliverables.*.starts_at' => ['nullable', 'date'],
            'deliverables.*.due_at' => ['required', 'date'],
            'deliverables.*.milestone_index' => ['nullable', 'integer', 'min:0'],
            'deliverables.*.milestone_uuid' => ['nullable', 'uuid', 'exists:research_milestones,uuid'],
            'deliverables.*.all_team_members' => ['sometimes', 'boolean'],
            'deliverables.*.assignee_uuids' => ['sometimes', 'array'],
            'deliverables.*.assignee_uuids.*' => ['uuid', 'exists:admins,uuid'],
            'deliverables.*.notify_all_team_members' => ['sometimes', 'boolean'],
            'deliverables.*.notify_admin_uuids' => ['sometimes', 'array'],
            'deliverables.*.notify_admin_uuids.*' => ['uuid', 'exists:admins,uuid'],
        ];
    }
}
