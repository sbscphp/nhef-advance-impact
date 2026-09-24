<?php

namespace App\Http\Requests\Admin\ConstituentManagement;

use App\Enums\ConstituentStatusEnum;
use App\Enums\ConstituentTypeEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class ConstituentListRequest extends ApiFormRequest
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
            ListingFilterRules::rules(['name']),
            [
                'filters.status' => ['sometimes', 'nullable', Rule::in(ConstituentStatusEnum::values())],
                'filters.constituent_type' => ['sometimes', 'nullable', Rule::in(ConstituentTypeEnum::values())],
                'export' => ['sometimes', 'nullable', 'string', Rule::in(['csv', 'pdf'])],
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages(), [
            'filters.status.in' => 'Status filter is invalid.',
            'export.in' => "Export format must be either 'csv' or 'pdf'.",
        ]);
    }
}
