<?php

namespace App\Http\Requests\Admin\Campaigns;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class CampaignInstitutionListRequest extends ApiFormRequest
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
        return array_merge(ListingFilterRules::rules(['name', 'value']), [
            'export' => ['sometimes', 'nullable', Rule::in(['csv', 'pdf'])],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages(), [
            'export.in' => "Export format must be either 'csv' or 'pdf'.",
        ]);
    }
}
