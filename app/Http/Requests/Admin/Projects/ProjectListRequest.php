<?php

namespace App\Http\Requests\Admin\Projects;

use App\Enums\ProjectCategoryEnum;
use App\Enums\ProjectStatusEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class ProjectListRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(ListingFilterRules::rules(['name', 'value']), [
            'filters.status' => ['sometimes', 'nullable', Rule::in(ProjectStatusEnum::values())],
            'filters.category' => ['sometimes', 'nullable', Rule::in(ProjectCategoryEnum::values())],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages());
    }
}
