<?php

namespace App\Http\Requests\Customer\Donations;

use App\Http\Requests\ApiFormRequest;

class ChargeDonationRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
