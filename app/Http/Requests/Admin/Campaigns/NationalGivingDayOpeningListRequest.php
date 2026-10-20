<?php

namespace App\Http\Requests\Admin\Campaigns;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;

class NationalGivingDayOpeningListRequest extends ApiFormRequest
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
            ListingFilterRules::rules(['name']),
            [
                // For the "Select National Day of Giving" campaign-creation dropdown, which
                // shouldn't offer an opening whose giving day has already passed.
                'filters.upcoming_only' => ['sometimes', 'nullable', 'boolean'],
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages(), [
            'filters.upcoming_only.boolean' => 'Upcoming only filter must be true or false.',
        ]);
    }
}
