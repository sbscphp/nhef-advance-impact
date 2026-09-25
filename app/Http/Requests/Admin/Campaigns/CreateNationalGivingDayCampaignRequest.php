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
            'assigned_admin_id' => ['required', 'uuid', 'exists:admins,uuid'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'description' => ['required', 'string'],
            'cover' => ['required', $this->campaignCoverRule()],
            'institutions' => ['required', 'array', 'min:1'],
            'institutions.*.institution_id' => ['required', 'uuid', 'exists:institutions,uuid'],
            'institutions.*.goal_amount' => ['required', 'numeric', 'min:0.01'],
            'institutions.*.currency' => ['required', Rule::in(CurrencyEnum::values())],
            'institutions.*.bank_account_id' => ['required', 'uuid', 'exists:bank_accounts,uuid'],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), $this->projectMessages(), [
            'assigned_admin_id.required' => 'Please select who this campaign is assigned to.',
            'institutions.required' => 'Please add at least one institution.',
            'institutions.min' => 'Please add at least one institution.',
            'institutions.*.institution_id.required' => 'Please select an institution.',
            'institutions.*.institution_id.exists' => 'The selected institution does not exist.',
            'institutions.*.goal_amount.required' => 'Please provide a goal for this institution.',
            'institutions.*.bank_account_id.required' => 'Please select or add a bank account for this institution.',
            'institutions.*.bank_account_id.exists' => 'The selected bank account does not exist.',
        ]);
    }

}
