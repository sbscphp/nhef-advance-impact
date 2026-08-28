<?php

namespace App\Http\Requests\Admin\Crm;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;

/**
 * Shared listing rules for a prospect's child records (calls, invites, proposals, messages):
 * none of the four tabs need filters beyond search/date-range/sort/pagination.
 */
class ProspectRelatedListRequest extends ApiFormRequest
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
        return ListingFilterRules::rules(['created_at']);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages());
    }
}
