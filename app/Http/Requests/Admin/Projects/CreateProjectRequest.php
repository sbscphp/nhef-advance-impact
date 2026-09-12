<?php

namespace App\Http\Requests\Admin\Projects;

use App\Enums\ProjectCategoryEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreateProjectRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'category' => ['required', Rule::in(ProjectCategoryEnum::values())],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'country' => ['required', 'string', 'max:255'],
            'state_lga' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2048'],

            'team_members' => ['sometimes', 'array'],
            'team_members.*.admin_uuid' => ['required_with:team_members', 'uuid', 'exists:admins,uuid'],
            'team_members.*.role_title' => ['nullable', 'string', 'max:255'],

            'manager_uuids' => ['required', 'array', 'min:1'],
            'manager_uuids.*' => ['uuid', 'exists:admins,uuid'],

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

            'funded_by_donor' => ['required', 'boolean'],
            'funded_by_donation' => ['required', 'boolean'],
            'donor_uuids' => ['required_if:funded_by_donor,true', 'array', 'min:1'],
            'donor_uuids.*' => ['uuid', 'exists:users,uuid'],
            'campaign_uuids' => ['required_if:funded_by_donation,true', 'array', 'min:1'],
            'campaign_uuids.*' => ['uuid', 'exists:campaigns,uuid'],
            'budget_min' => ['required', 'numeric', 'min:0'],
            'budget_max' => ['required', 'numeric', 'gte:budget_min'],

            // A disbursement phase is a budget line created inline; adding one is optional.
            'budget_lines' => ['sometimes', 'array'],
            'budget_lines.*.title' => ['required_with:budget_lines', 'string', 'max:255'],
            'budget_lines.*.reference_id' => ['nullable', 'string', 'max:255'],
            'budget_lines.*.amount_allocated' => ['required_with:budget_lines', 'numeric', 'min:0'],
            'budget_lines.*.starts_at' => ['nullable', 'date'],
            'budget_lines.*.due_at' => ['nullable', 'date', 'after_or_equal:budget_lines.*.starts_at'],
            'budget_lines.*.all_team_members' => ['sometimes', 'boolean'],
            'budget_lines.*.recipient_admin_uuids' => ['sometimes', 'array'],
            'budget_lines.*.recipient_admin_uuids.*' => ['uuid', 'exists:admins,uuid'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->boolean('funded_by_donor') && ! $this->boolean('funded_by_donation')) {
                $validator->errors()->add('funded_by_donor', 'At least one funding type (donor or donation) must be selected.');
            }
        });
    }
}
