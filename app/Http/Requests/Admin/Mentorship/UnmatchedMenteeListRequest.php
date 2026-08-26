<?php

namespace App\Http\Requests\Admin\Mentorship;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;

class UnmatchedMenteeListRequest extends ApiFormRequest
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
        return ListingFilterRules::rules(['name']);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages());
    }
}
