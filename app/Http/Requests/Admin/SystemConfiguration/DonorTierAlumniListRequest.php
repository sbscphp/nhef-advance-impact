<?php

namespace App\Http\Requests\Admin\SystemConfiguration;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class DonorTierAlumniListRequest extends ApiFormRequest
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
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'institution' => ['sometimes', 'nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
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
