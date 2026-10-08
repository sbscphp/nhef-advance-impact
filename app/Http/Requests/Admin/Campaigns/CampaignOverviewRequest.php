<?php

namespace App\Http\Requests\Admin\Campaigns;

use App\Enums\CampaignTypeEnum;
use App\Http\Requests\Admin\DateRangeStatsRequest;
use Illuminate\Validation\Rule;

class CampaignOverviewRequest extends DateRangeStatsRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'type' => ['sometimes', 'nullable', Rule::in(CampaignTypeEnum::values())],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ['type.in' => 'Type filter is invalid.']);
    }
}
