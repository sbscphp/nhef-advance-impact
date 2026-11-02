<?php

namespace App\Http\Requests\Admin\Donation;

use App\Enums\CurrencyEnum;
use App\Enums\PaymentMethodEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class RecordDonationRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'donor_uuid' => ['required', 'uuid', Rule::exists('users', 'uuid')],
            'campaign_uuid' => ['required', 'uuid', Rule::exists('campaigns', 'uuid')],
            'amount' => ['required', 'numeric', 'min:100'],
            'currency' => ['required', Rule::in(CurrencyEnum::values())],
            'payment_method' => ['required', Rule::in(PaymentMethodEnum::values())],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
