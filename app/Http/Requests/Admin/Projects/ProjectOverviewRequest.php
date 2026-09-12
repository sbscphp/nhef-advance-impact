<?php

namespace App\Http\Requests\Admin\Projects;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;

class ProjectOverviewRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ListingFilterRules::periodDateRules();
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::periodDateMessages());
    }
}
