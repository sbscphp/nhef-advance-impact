<?php

namespace App\Http\Requests\Admin\Campaigns;

use App\Enums\CurrencyEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ValidatesCampaignInput;
use Illuminate\Validation\Rule;

class CreateNationalGivingDayCampaignRequest extends ApiFormRequest
{
    use ValidatesCampaignInput;

    protected function prepareForValidation(): void
    {
        $this->decodeJsonInputs(['institutions', 'projects']);
        $this->mergeAssigneeAlias();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->projectAndTimerRules(), [
            'title' => ['required', 'string', 'max:255'],
            // The campaign inherits starts_at/ends_at from this opening - see
            // CampaignService::createNationalGivingDay()'s docblock.
            'opening_uuid' => ['required', 'uuid', 'exists:national_giving_day_openings,uuid'],
            'assigned_admin_id' => ['required', 'uuid', 'exists:admins,uuid'],
            'description' => ['required', 'string'],
            'cover' => ['required', $this->campaignCoverRule()],
            // Overrides projectAndTimerRules()'s optional 'projects': the campaign's target is the
            // sum of its projects' goals, so a National Giving Day campaign needs at least one.
            'projects' => ['required', 'array', 'min:1'],
            'institutions' => ['required', 'array', 'min:1'],
            'institutions.*.institution_id' => ['required', 'uuid', 'exists:institutions,uuid'],
            'institutions.*.currency' => ['required', Rule::in(CurrencyEnum::values())],
            'institutions.*.bank_account_id' => ['required', 'uuid', 'exists:bank_accounts,uuid'],
            ...$this->projectGoalRules('institutions.*.'),
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), $this->projectMessages(), $this->projectGoalMessages('institutions.*.'), [
            'opening_uuid.required' => 'Please select which National Giving Day opening this campaign belongs to.',
            'opening_uuid.exists' => 'The selected National Giving Day opening does not exist.',
            'assigned_admin_id.required' => 'Please select who this campaign is assigned to.',
            'projects.required' => 'Please add at least one project.',
            'projects.min' => 'Please add at least one project.',
            'institutions.required' => 'Please add at least one institution.',
            'institutions.min' => 'Please add at least one institution.',
            'institutions.*.institution_id.required' => 'Please select an institution.',
            'institutions.*.institution_id.exists' => 'The selected institution does not exist.',
            'institutions.*.bank_account_id.required' => 'Please select or add a bank account for this institution.',
            'institutions.*.bank_account_id.exists' => 'The selected bank account does not exist.',
        ]);
    }
}
