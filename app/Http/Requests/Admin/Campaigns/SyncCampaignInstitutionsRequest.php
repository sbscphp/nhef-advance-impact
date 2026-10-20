<?php

namespace App\Http\Requests\Admin\Campaigns;

use App\Enums\CurrencyEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ValidatesCampaignInput;
use Illuminate\Validation\Rule;

class SyncCampaignInstitutionsRequest extends ApiFormRequest
{
    use ValidatesCampaignInput;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->projectGoalRules('institutions.*.'), [
            'institutions' => ['required', 'array', 'min:1'],
            'institutions.*.institution_id' => ['required', 'uuid', 'exists:institutions,uuid', 'distinct'],
            'institutions.*.currency' => ['required', Rule::in(CurrencyEnum::values())],
            'institutions.*.bank_account_id' => ['required', 'uuid', 'exists:bank_accounts,uuid'],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), $this->projectGoalMessages('institutions.*.'), [
            'institutions.required' => 'Please add at least one institution.',
            'institutions.min' => 'Please add at least one institution.',
            'institutions.*.institution_id.required' => 'Please select an institution.',
            'institutions.*.institution_id.exists' => 'The selected institution does not exist.',
            'institutions.*.institution_id.distinct' => 'Each institution can only be allocated once per campaign.',
            'institutions.*.bank_account_id.required' => 'Please select or add a bank account for this institution.',
            'institutions.*.bank_account_id.exists' => 'The selected bank account does not exist.',
        ]);
    }
}
