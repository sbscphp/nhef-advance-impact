<?php

namespace App\Http\Requests\Customer\Donations;

use App\Enums\DonationFrequencyEnum;
use App\Enums\PaymentMethodEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class MakeDonationRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'campaign_uuid' => ['required', 'uuid', Rule::exists('campaigns', 'uuid')],
            'frequency' => ['required', Rule::in(DonationFrequencyEnum::values())],
            'amount' => ['required', 'numeric', 'min:100'],
            'is_anonymous' => ['sometimes', 'boolean'],
            'payment_method' => ['required', Rule::in(PaymentMethodEnum::values())],
        ];
    }
}
