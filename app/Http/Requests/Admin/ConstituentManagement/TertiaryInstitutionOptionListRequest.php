<?php

namespace App\Http\Requests\Admin\ConstituentManagement;

use App\Http\Requests\ApiFormRequest;

class TertiaryInstitutionOptionListRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('available_only')) {
            $parsed = filter_var($this->input('available_only'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($parsed !== null) {
                $this->merge(['available_only' => $parsed]);
            }
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'available_only' => ['sometimes', 'nullable', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
