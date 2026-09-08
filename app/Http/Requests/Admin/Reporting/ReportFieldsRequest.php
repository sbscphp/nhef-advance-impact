<?php

namespace App\Http\Requests\Admin\Reporting;

use App\Enums\ReportDatasetEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class ReportFieldsRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['dataset' => $this->route('dataset')]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'dataset' => ['required', Rule::in(ReportDatasetEnum::values())],
        ];
    }
}
