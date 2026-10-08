<?php

namespace App\Http\Requests\Campaigns;

use App\Enums\CampaignTypeEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class CampaignListRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(
            ListingFilterRules::rules(['name', 'value']),
            [
                'filters.type' => ['sometimes', 'nullable', Rule::in(CampaignTypeEnum::values())],
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages(), [
            'filters.type.in' => 'Type filter is invalid.',
        ]);
    }
}
