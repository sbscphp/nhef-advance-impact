<?php

namespace App\Http\Requests\Customer\Donations;

use App\Http\Requests\ApiFormRequest;

class DonationHistoryOverviewRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
