<?php

namespace App\Http\Requests\Admin\ConstituentManagement;

use App\Enums\DonationStatusEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class ConstituentDonationListRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        ListingFilterRules::applyPeriodDateRangeToRequest($this);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(
            ListingFilterRules::rules(['name', 'value']),
            [
                'status' => ['sometimes', 'nullable', Rule::in(DonationStatusEnum::values())],
                'is_recurring' => ['sometimes', 'nullable', 'boolean'],
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages());
    }
}
