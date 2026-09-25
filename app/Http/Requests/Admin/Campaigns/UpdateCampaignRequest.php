<?php

namespace App\Http\Requests\Admin\Campaigns;

use App\Enums\CurrencyEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ValidatesCampaignInput;
use Illuminate\Validation\Rule;

class UpdateCampaignRequest extends ApiFormRequest
{
    use ValidatesCampaignInput;

    protected function prepareForValidation(): void
    {
        $this->decodeJsonInputs(['projects']);
        $this->mergeAssigneeAlias();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->projectAndTimerRules(), [
            'title' => ['sometimes', 'string', 'max:255'],
            'goal_amount' => ['sometimes', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', Rule::in(CurrencyEnum::values())],
            'assigned_admin_id' => ['sometimes', 'uuid', 'exists:admins,uuid'],
            'bank_account_id' => ['sometimes', 'uuid', 'exists:bank_accounts,uuid'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'description' => ['sometimes', 'string'],
            'cover' => ['sometimes', 'nullable', $this->campaignCoverRule(true)],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), $this->projectMessages(), [
            'assigned_admin_id.exists' => 'The selected officer does not exist.',
            'bank_account_id.exists' => 'The selected bank account does not exist.',
        ]);
    }
}
