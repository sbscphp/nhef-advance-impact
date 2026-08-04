<?php

namespace App\Http\Requests\Customer\Donations;

use App\Enums\DonationStatusEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class DonationListRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('is_recurring')) {
            return;
        }

        $value = $this->input('is_recurring');

        if (is_bool($value)) {
            return;
        }

        if (is_string($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($parsed !== null) {
                $this->merge(['is_recurring' => $parsed]);
            }
        }
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
                // True for the "Recurring Donations" view (monthly/quarterly/annually only); false
                // for one-time donations only. Omit to see both.
                'is_recurring' => ['sometimes', 'nullable', 'boolean'],
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages());
    }
}
