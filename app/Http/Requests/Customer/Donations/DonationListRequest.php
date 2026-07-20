<?php

namespace App\Http\Requests\Customer\Donations;

use App\Enums\DonationStatusEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class DonationListRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::in(DonationStatusEnum::values())],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
