<?php

namespace App\Http\Requests\Admin\Projects;

use App\Enums\ProjectRiskSeverityEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class ProjectIssueListRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(ListingFilterRules::rules(['title']), [
            'filters.severity' => ['sometimes', 'nullable', Rule::in(ProjectRiskSeverityEnum::values())],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages());
    }
}
