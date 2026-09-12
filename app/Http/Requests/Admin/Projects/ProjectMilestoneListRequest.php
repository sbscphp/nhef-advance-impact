<?php

namespace App\Http\Requests\Admin\Projects;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class ProjectMilestoneListRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(ListingFilterRules::rules(['name']), [
            'filters.status' => ['sometimes', 'nullable', Rule::in(['in_progress', 'overdue', 'completed'])],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages());
    }
}
