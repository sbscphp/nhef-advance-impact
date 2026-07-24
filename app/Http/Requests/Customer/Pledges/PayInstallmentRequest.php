<?php

namespace App\Http\Requests\Customer\Pledges;

use App\Enums\PaymentMethodEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class PayInstallmentRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', Rule::in(PaymentMethodEnum::values())],
        ];
    }
}
