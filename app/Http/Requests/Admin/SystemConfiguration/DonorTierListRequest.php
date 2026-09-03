<?php

namespace App\Http\Requests\Admin\SystemConfiguration;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class DonorTierListRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        ListingFilterRules::applyPeriodDateRangeToRequest($this);

        if ($this->has('export') && is_string($this->input('export'))) {
            $this->merge(['export' => strtolower(trim($this->input('export')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(
            ListingFilterRules::rules(['name', 'value']),
            [
                'filters.status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive'])],
                'export' => ['sometimes', 'nullable', 'string', Rule::in(['csv', 'pdf'])],
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages(), [
            'export.in' => "Export format must be either 'csv' or 'pdf'.",
        ]);
    }
}
