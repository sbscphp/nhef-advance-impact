<?php

namespace App\Http\Requests\Customer\Pledges;

use App\Enums\PaymentMethodEnum;
use App\Enums\PledgeFrequencyEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class MakePledgeRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'campaign_uuid' => ['required', 'uuid', Rule::exists('campaigns', 'uuid')],
            'frequency' => ['required', Rule::in(PledgeFrequencyEnum::values())],
            'total_amount' => ['required', 'numeric', 'min:100'],
            'start_date' => [
                Rule::requiredIf(fn () => $this->input('frequency') !== PledgeFrequencyEnum::ONE_TIME->value),
                Rule::prohibitedIf(fn () => $this->input('frequency') === PledgeFrequencyEnum::ONE_TIME->value),
                'nullable',
                'date',
                'after_or_equal:today',
            ],
            'end_date' => [
                Rule::requiredIf(fn () => $this->input('frequency') !== PledgeFrequencyEnum::ONE_TIME->value),
                Rule::prohibitedIf(fn () => $this->input('frequency') === PledgeFrequencyEnum::ONE_TIME->value),
                'nullable',
                'date',
                'after:start_date',
            ],
            'expected_payment_date' => [
                Rule::requiredIf(fn () => $this->input('frequency') === PledgeFrequencyEnum::ONE_TIME->value),
                Rule::prohibitedIf(fn () => $this->input('frequency') !== PledgeFrequencyEnum::ONE_TIME->value),
                'nullable',
                'date',
                'after_or_equal:today',
            ],
            'is_anonymous' => ['sometimes', 'boolean'],
            'payment_method' => ['required', Rule::in(PaymentMethodEnum::values())],
        ];
    }
}
