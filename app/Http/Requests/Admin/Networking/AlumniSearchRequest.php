<?php

namespace App\Http\Requests\Admin\Networking;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;

/** Backs the "Search & Select Alumni" step; matches by name, email, or organisation. */
class AlumniSearchRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        ListingFilterRules::applyPeriodDateRangeToRequest($this);

        // Only one sortable field exists (name); default to it so sort_direction alone is enough.
        $this->merge(['sort_by' => $this->input('sort_by', 'name')]);
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
