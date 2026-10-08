<?php

namespace App\Http\Requests\Admin\Dashboard;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

/** Query parameters shared by the Super Admin dashboard cards; each card reads the ones it needs. */
class DashboardSectionRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        ListingFilterRules::applyPeriodDateRangeToRequest($this);
    }

    public function rules(): array
    {
        return array_merge(ListingFilterRules::periodDateRules(), [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'filters.status' => ['sometimes', 'nullable', Rule::in(['scheduled', 'ongoing', 'completed', 'archived', 'draft', 'cancelled', 'deactivated'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::periodDateMessages(), [
            'filters.status.in' => 'Status filter is invalid.',
            'per_page.max' => 'Per page may not be greater than 100.',
            'limit.max' => 'Limit may not be greater than 50.',
        ]);
    }
}
