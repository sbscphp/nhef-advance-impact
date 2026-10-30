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
                // Lets NHEF pull one institution's campaigns at a time, for a "Fundraising
                // Campaigns Across Partner Institutions" block-per-institution view (client
                // feedback, 2026-10-16).
                'filters.institution_uuid' => ['sometimes', 'nullable', 'uuid', Rule::exists('institutions', 'uuid')],
                // For the "List of National Day of Giving" drill-through: finds the campaign(s)
                // created under one selected opening (one per institution).
                'filters.opening_uuid' => ['sometimes', 'nullable', 'uuid', Rule::exists('national_giving_day_openings', 'uuid')],
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages(), [
            'filters.status.in' => 'Status filter is invalid.',
            'filters.type.in' => 'Type filter is invalid.',
            'filters.institution_uuid.exists' => 'The selected institution does not exist.',
            'filters.opening_uuid.exists' => 'The selected National Giving Day opening does not exist.',
        ]);
    }
}
