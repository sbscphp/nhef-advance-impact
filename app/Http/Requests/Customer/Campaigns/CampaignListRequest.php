<?php

namespace App\Http\Requests\Customer\Campaigns;

use App\Enums\CampaignCategoryEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class CampaignListRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'category' => ['sometimes', 'nullable', Rule::in(CampaignCategoryEnum::values())],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
