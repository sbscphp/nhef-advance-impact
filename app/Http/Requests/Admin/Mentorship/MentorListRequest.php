<?php

namespace App\Http\Requests\Admin\Mentorship;

use App\Enums\MentorReviewStatusEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class MentorListRequest extends ApiFormRequest
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
            ListingFilterRules::rules(['name']),
            [
                'filters.status' => ['sometimes', 'nullable', Rule::in(MentorReviewStatusEnum::values())],
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages(), [
            'filters.status.in' => 'Status filter is invalid.',
        ]);
    }
}
