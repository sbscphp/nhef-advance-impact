<?php

namespace App\Http\Requests\Admin\ConstituentManagement;

use App\Enums\PledgeStatusEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class ConstituentPledgeListRequest extends ApiFormRequest
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
                'status' => ['sometimes', 'nullable', Rule::in(PledgeStatusEnum::values())],
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages());
    }
}
