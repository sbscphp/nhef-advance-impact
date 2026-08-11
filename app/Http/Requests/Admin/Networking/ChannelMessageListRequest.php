<?php

namespace App\Http\Requests\Admin\Networking;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;

class ChannelMessageListRequest extends ApiFormRequest
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
        // No sort_by columns: messages only ever order by their own created_at, so
        // sort_direction alone (newest vs oldest first) covers the only meaningful choice.
        return ListingFilterRules::rules([]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages());
    }
}
