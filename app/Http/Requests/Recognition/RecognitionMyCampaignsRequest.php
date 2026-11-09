<?php

namespace App\Http\Requests\Recognition;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class RecognitionMyCampaignsRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            // `position` for the top-three cards (best position first = ascending), `total` for
            // the table (highest given first by default).
            'sort_by' => ['sometimes', 'nullable', 'string', Rule::in(['position', 'total'])],
            'sort_direction' => ['sometimes', 'nullable', 'string', Rule::in(['asc', 'desc', 'ASC', 'DESC'])],
        ];
    }
}
