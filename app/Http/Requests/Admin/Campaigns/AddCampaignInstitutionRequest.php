<?php

namespace App\Http\Requests\Admin\Campaigns;

use App\Enums\CurrencyEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ValidatesCampaignInput;
use Illuminate\Validation\Rule;

class AddCampaignInstitutionRequest extends ApiFormRequest
{
    use ValidatesCampaignInput;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->projectGoalRules(), [
            'institution_id' => ['required', 'uuid', 'exists:institutions,uuid'],
            'currency' => ['required', Rule::in(CurrencyEnum::values())],
            'bank_account_id' => ['required', 'uuid', 'exists:bank_accounts,uuid'],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), $this->projectGoalMessages(), [
            'institution_id.required' => 'Please select an institution.',
            'institution_id.exists' => 'The selected institution does not exist.',
            'bank_account_id.required' => 'Please select or add a bank account for this institution.',
            'bank_account_id.exists' => 'The selected bank account does not exist.',
        ]);
    }
}
