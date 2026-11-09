<?php

namespace App\Http\Requests\Recognition;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class LeaderboardListRequest extends ApiFormRequest
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
            // `rank`/`total` are inversely sorted (rank 1 = highest total): the service translates
            // `sort_by=rank` into the right `total` ordering, since rank isn't a real column.
            'sort_by' => ['sometimes', 'nullable', 'string', Rule::in(['rank', 'total', 'donor_name'])],
            'sort_direction' => ['sometimes', 'nullable', 'string', Rule::in(['asc', 'desc', 'ASC', 'DESC'])],
        ];
    }
}
