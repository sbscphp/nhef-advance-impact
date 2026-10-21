<?php

namespace App\Http\Requests\Admin\ConstituentManagement;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class InstitutionAdminListRequest extends ApiFormRequest
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
                'filters.status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive'])],
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages(), [
            'filters.status.in' => "Status filter must be either 'active' or 'inactive'.",
        ]);
    }
}
