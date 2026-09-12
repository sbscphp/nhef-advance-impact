<?php

namespace App\Http\Requests\Admin\Projects;

use App\Http\Requests\ApiFormRequest;

class GenerateProjectReportRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date', 'required_with:end_date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', 'required_with:start_date'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'start_date.required_with' => 'Start date is required when an end date is given.',
            'end_date.required_with' => 'End date is required when a start date is given.',
        ]);
    }
}
