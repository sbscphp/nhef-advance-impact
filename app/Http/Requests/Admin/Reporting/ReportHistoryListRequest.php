<?php

namespace App\Http\Requests\Admin\Reporting;

use App\Enums\ReportDatasetEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class ReportHistoryListRequest extends ApiFormRequest
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
            ListingFilterRules::rules(['name', 'created_at']),
            [
                'filters.dataset' => ['sometimes', 'nullable', Rule::in(ReportDatasetEnum::values())],
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages(), [
            'filters.dataset.in' => 'Dataset filter is invalid.',
        ]);
    }
}
