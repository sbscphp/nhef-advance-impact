<?php

namespace App\Http\Requests\Admin\Campaigns;

use App\Enums\CurrencyEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ValidatesCampaignInput;
use Illuminate\Validation\Rule;

class CreateCampaignRequest extends ApiFormRequest
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
            'goal_amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', Rule::in(CurrencyEnum::values())],
            'assigned_admin_id' => ['required', 'uuid', 'exists:admins,uuid'],
            'bank_account_id' => ['required', 'uuid', 'exists:bank_accounts,uuid'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'description' => ['required', 'string'],
            'cover' => ['required', $this->campaignCoverRule()],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'assigned_admin_id.required' => 'Please select an officer to assign this campaign to.',
            'assigned_admin_id.exists' => 'The selected officer does not exist.',
            'bank_account_id.required' => 'Please select or add a bank account for remittance.',
            'bank_account_id.exists' => 'The selected bank account does not exist.',
        ]);
    }
}
