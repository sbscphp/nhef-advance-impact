<?php

namespace App\Http\Requests\Admin\Projects;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class ProjectExpenditureListRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(ListingFilterRules::rules(['name', 'value']), [
            'filters.budget_line_uuid' => ['sometimes', 'nullable', 'uuid'],
            'export' => ['sometimes', 'nullable', 'string', Rule::in(['csv', 'pdf'])],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages());
    }
}
