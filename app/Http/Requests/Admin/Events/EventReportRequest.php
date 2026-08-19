<?php

namespace App\Http\Requests\Admin\Events;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class EventReportRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('export') && is_string($this->input('export'))) {
            $this->merge(['export' => strtolower(trim($this->input('export')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'export' => ['sometimes', 'nullable', 'string', Rule::in(['csv', 'pdf'])],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'export.in' => "Export format must be either 'csv' or 'pdf'.",
        ]);
    }
}
