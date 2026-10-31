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
                // Always an array, even for a single type (e.g. ["alumni"]), so the FE has one
                // shape to send regardless of whether it's filtering to one type (Alumni tab) or
                // several (an "Other Constituent" tab showing non_alumni + organization together).
                'filters.constituent_type' => ['sometimes', 'nullable', 'array'],
                'filters.constituent_type.*' => [Rule::in(ConstituentTypeEnum::values())],
                'filters.tertiary_institution_uuid' => ['sometimes', 'nullable', 'uuid', Rule::exists('tertiary_institutions', 'uuid')],
                'filters.department' => ['sometimes', 'nullable', 'string', 'max:255'],
                'filters.graduation_year' => ['sometimes', 'nullable', 'integer', 'min:1960', 'max:'.now()->year],
                'export' => ['sometimes', 'nullable', 'string', Rule::in(['csv', 'pdf'])],
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages(), [
            'filters.status.in' => 'Status filter is invalid.',
            'filters.tertiary_institution_uuid.exists' => 'Institution filter must be a valid institution.',
            'filters.graduation_year.integer' => 'Graduation year must be a valid year.',
            'filters.graduation_year.min' => 'Graduation year is too far in the past.',
            'filters.graduation_year.max' => 'Graduation year cannot be in the future.',
            'export.in' => "Export format must be either 'csv' or 'pdf'.",
        ]);
    }
}
