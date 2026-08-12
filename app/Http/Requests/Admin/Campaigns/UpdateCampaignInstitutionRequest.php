<?php

namespace App\Http\Requests\Admin\Campaigns;

use App\Enums\CurrencyEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignInstitutionRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'goal_amount' => ['sometimes', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', Rule::in(CurrencyEnum::values())],
            'bank_account_id' => ['sometimes', 'uuid', 'exists:bank_accounts,uuid'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'bank_account_id.exists' => 'The selected bank account does not exist.',
        ]);
    }
}
