<?php

namespace App\Http\Requests\Admin\Projects;

use App\Enums\ProjectDocumentCategoryEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class ProjectDocumentListRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(ListingFilterRules::rules(['name']), [
            'filters.category' => ['sometimes', 'nullable', Rule::in(ProjectDocumentCategoryEnum::values())],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages());
    }
}
