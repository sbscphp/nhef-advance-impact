<?php

namespace App\Http\Requests\Admin\Campaigns;

use App\Enums\CampaignTypeEnum;
use App\Enums\CurrencyEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ValidatesCampaignInput;
use Illuminate\Validation\Rule;

/**
 * One shared endpoint for both campaign kinds; `type` (defaults to "standard" when omitted, so
 * every existing caller keeps working unchanged) decides which fields below apply. The dedicated
 * `POST /campaigns/national-giving-day` endpoint ({@see CreateNationalGivingDayCampaignRequest})
 * still works too, kept for backward compatibility, not merged into this class.
 */
class CreateCampaignRequest extends ApiFormRequest
{
    use ValidatesCampaignInput;

    protected function prepareForValidation(): void
    {
        $this->decodeJsonInputs(['institutions', 'projects']);
        $this->mergeAssigneeAlias();

        if (! $this->filled('type')) {
            $this->merge(['type' => CampaignTypeEnum::STANDARD->value]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(
            $this->commonRules(),
            $this->isNationalGivingDay() ? $this->nationalGivingDayRules() : $this->standardRules(),
        );
    }

    private function isNationalGivingDay(): bool
    {
        return $this->input('type') === CampaignTypeEnum::NATIONAL_GIVING_DAY->value;
    }

    /** Fields both campaign kinds collect. */
    private function commonRules(): array
    {
        return [
            'type' => ['sometimes', Rule::in(CampaignTypeEnum::values())],
            'title' => ['required', 'string', 'max:255'],
            'assigned_admin_id' => ['required', 'uuid', 'exists:admins,uuid'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'description' => ['required', 'string'],
            'cover' => ['required', $this->campaignCoverRule()],
        ];
    }

    private function standardRules(): array
    {
        return [
            'goal_amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', Rule::in(CurrencyEnum::values())],
            'bank_account_id' => ['required', 'uuid', 'exists:bank_accounts,uuid'],
        ];
    }

    /** Same shape as {@see CreateNationalGivingDayCampaignRequest}. */
    private function nationalGivingDayRules(): array
    {
        return array_merge($this->projectAndTimerRules(), [
            // Overrides projectAndTimerRules()'s optional 'projects': the campaign's target is the
            // sum of its projects' goals, so a National Giving Day campaign needs at least one.
            'projects' => ['required', 'array', 'min:1'],
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
            'assigned_admin_id.required' => 'Please select an officer to assign this campaign to.',
            'assigned_admin_id.exists' => 'The selected officer does not exist.',
            'bank_account_id.required' => 'Please select or add a bank account for remittance.',
            'bank_account_id.exists' => 'The selected bank account does not exist.',
            'projects.required' => 'Please add at least one project.',
            'projects.min' => 'Please add at least one project.',
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
