<?php

namespace App\Http\Requests\Admin\Communications;

use App\Http\Requests\ApiFormRequest;

/** Powers the "Select a donor" constituent picker used by the mail composer and call-log form. */
class ConstituentSegmentListRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tertiary_institution_uuid' => ['sometimes', 'nullable', 'uuid', 'exists:tertiary_institutions,uuid'],
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'graduation_year_from' => ['sometimes', 'nullable', 'integer', 'digits:4'],
            'graduation_year_to' => ['sometimes', 'nullable', 'integer', 'digits:4', 'gte:graduation_year_from'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
