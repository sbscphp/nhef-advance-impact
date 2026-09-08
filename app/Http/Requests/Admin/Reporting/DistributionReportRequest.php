<?php

namespace App\Http\Requests\Admin\Reporting;

use App\Enums\ReportDatasetEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class DistributionReportRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['dataset' => $this->route('dataset')]);
        ListingFilterRules::applyPeriodDateRangeToRequest($this);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(ListingFilterRules::periodDateRules(), [
            'dataset' => ['required', Rule::in(ReportDatasetEnum::values())],
            'field' => ['required', 'string'],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::periodDateMessages());
    }
}
