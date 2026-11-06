<?php

namespace App\Http\Requests\Admin\Reporting;

use App\Enums\ReportDatasetEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class GenerateReportRequest extends ApiFormRequest
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
        return array_merge(ListingFilterRules::periodDateRules(), [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dataset' => ['required', Rule::in(ReportDatasetEnum::values())],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'institution_uuid' => ['sometimes', 'nullable', 'string', 'exists:institutions,uuid'],
            // Alternative to institution_uuid for a frontend picker that only has the name on
            // hand - institutions.name has a real DB-level unique constraint, so this is unambiguous.
            'institution_name' => ['sometimes', 'nullable', 'string', 'exists:institutions,name'],
            // Discrete per-field filter, e.g. {"constituent_type": "non_alumni"} - which keys are
            // valid depends on the chosen dataset's own groupableFields(), not validated here;
            // an inapplicable key is ignored, not errored, by ReportBuilderService::applyFilters().
            'filters' => ['sometimes', 'nullable', 'array'],
            'filters.*' => ['sometimes', 'nullable', 'string', 'max:255'],
            'format' => ['required', Rule::in(['csv', 'pdf'])],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::periodDateMessages());
    }
}
