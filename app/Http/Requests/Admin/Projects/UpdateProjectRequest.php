<?php

namespace App\Http\Requests\Admin\Projects;

use App\Enums\ProjectCategoryEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'category' => ['sometimes', 'nullable', Rule::in(ProjectCategoryEnum::values())],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'state_lga' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:2048'],

            'team_members' => ['sometimes', 'array'],
            'team_members.*.admin_uuid' => ['required_with:team_members', 'uuid', 'exists:admins,uuid'],
            'team_members.*.role_title' => ['nullable', 'string', 'max:255'],

            'manager_uuids' => ['sometimes', 'array'],
            'manager_uuids.*' => ['uuid', 'exists:admins,uuid'],

            // Full replace-set sync, matching the Edit Project wizard: any existing objective/
            // milestone/deliverable not included here (by uuid) is deleted, same as removing it
            // from the list in the wizard before Save Changes. Omit uuid to create a new one.
            'objectives' => ['sometimes', 'array'],
            'objectives.*.uuid' => ['nullable', 'uuid', 'exists:project_objectives,uuid'],
            'objectives.*.title' => ['required', 'string', 'max:255'],
            'objectives.*.description' => ['nullable', 'string'],
            'objectives.*.all_team_members' => ['sometimes', 'boolean'],
            'objectives.*.assignee_uuids' => ['sometimes', 'array'],
            'objectives.*.assignee_uuids.*' => ['uuid', 'exists:admins,uuid'],

            'milestones' => ['sometimes', 'array'],
            'milestones.*.uuid' => ['nullable', 'uuid', 'exists:project_milestones,uuid'],
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
            'deliverables.*.uuid' => ['nullable', 'uuid', 'exists:project_deliverables,uuid'],
            'deliverables.*.title' => ['required', 'string', 'max:255'],
            'deliverables.*.starts_at' => ['nullable', 'date'],
            'deliverables.*.due_at' => ['required', 'date'],
            'deliverables.*.milestone_index' => ['nullable', 'integer', 'min:0'],
            'deliverables.*.milestone_uuid' => ['nullable', 'uuid', 'exists:project_milestones,uuid'],
            'deliverables.*.all_team_members' => ['sometimes', 'boolean'],
            'deliverables.*.assignee_uuids' => ['sometimes', 'array'],
            'deliverables.*.assignee_uuids.*' => ['uuid', 'exists:admins,uuid'],
            'deliverables.*.notify_all_team_members' => ['sometimes', 'boolean'],
            'deliverables.*.notify_admin_uuids' => ['sometimes', 'array'],
            'deliverables.*.notify_admin_uuids.*' => ['uuid', 'exists:admins,uuid'],

            'funded_by_donor' => ['sometimes', 'boolean'],
            'funded_by_donation' => ['sometimes', 'boolean'],
            'donor_uuids' => ['sometimes', 'array'],
            'donor_uuids.*' => ['uuid', 'exists:users,uuid'],
            'campaign_uuids' => ['sometimes', 'array'],
            'campaign_uuids.*' => ['uuid', 'exists:campaigns,uuid'],
            'budget_min' => ['sometimes', 'numeric', 'min:0'],
            'budget_max' => ['sometimes', 'numeric', 'gte:budget_min'],

            'budget_lines' => ['sometimes', 'array'],
            'budget_lines.*.uuid' => ['nullable', 'uuid', 'exists:project_budget_lines,uuid'],
            'budget_lines.*.title' => ['required', 'string', 'max:255'],
            'budget_lines.*.reference_id' => ['nullable', 'string', 'max:255'],
            'budget_lines.*.amount_allocated' => ['required', 'numeric', 'min:0'],
            'budget_lines.*.starts_at' => ['nullable', 'date'],
            'budget_lines.*.due_at' => ['nullable', 'date', 'after_or_equal:budget_lines.*.starts_at'],
            'budget_lines.*.all_team_members' => ['sometimes', 'boolean'],
            'budget_lines.*.recipient_admin_uuids' => ['sometimes', 'array'],
            'budget_lines.*.recipient_admin_uuids.*' => ['uuid', 'exists:admins,uuid'],
        ];
    }
}
