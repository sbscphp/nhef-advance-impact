<?php

namespace App\Http\Requests\Customer\Pledges;

use App\Http\Requests\ApiFormRequest;

class PayInstallmentRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
