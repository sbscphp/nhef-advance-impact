<?php

namespace App\Http\Requests\Admin\Campaigns;

use App\Enums\CurrencyEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ValidatesCampaignInput;
use Illuminate\Validation\Rule;

class UpdateCampaignInstitutionRequest extends ApiFormRequest
{
    use ValidatesCampaignInput;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->projectGoalRules(required: false), [
            'currency' => ['sometimes', Rule::in(CurrencyEnum::values())],
            'bank_account_id' => ['sometimes', 'uuid', 'exists:bank_accounts,uuid'],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), $this->projectGoalMessages(), [
            'bank_account_id.exists' => 'The selected bank account does not exist.',
        ]);
    }
}
