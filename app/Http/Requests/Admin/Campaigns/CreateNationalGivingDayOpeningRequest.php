<?php

namespace App\Http\Requests\Admin\Campaigns;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ValidatesCampaignInput;

class CreateNationalGivingDayOpeningRequest extends ApiFormRequest
{
    use ValidatesCampaignInput;

    protected function prepareForValidation(): void
    {
        $this->mergeAssigneeAlias();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'cover' => ['sometimes', 'nullable', $this->campaignCoverRule(nullable: true)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'assigned_admin_id' => ['required', 'uuid', 'exists:admins,uuid'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'title.required' => 'Please give this opening a title.',
            'starts_at.required' => 'Please set the opening date.',
            'assigned_admin_id.required' => 'Please select who this opening is assigned to.',
        ]);
    }
}
