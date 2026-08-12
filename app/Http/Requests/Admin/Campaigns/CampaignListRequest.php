<?php

namespace App\Http\Requests\Admin\Campaigns;

use App\Enums\CampaignStatusEnum;
use App\Enums\CampaignTypeEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class CampaignListRequest extends ApiFormRequest
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
                'filters.status' => ['sometimes', 'nullable', Rule::in(CampaignStatusEnum::values())],
                'filters.type' => ['sometimes', 'nullable', Rule::in(CampaignTypeEnum::values())],
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages(), [
            'filters.status.in' => 'Status filter is invalid.',
            'filters.type.in' => 'Type filter is invalid.',
        ]);
    }
}
