<?php

namespace App\Http\Requests\Admin\Projects;

use App\Enums\ProjectBroadcastDeliveryEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class ProjectBroadcastListRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(ListingFilterRules::rules(['title']), [
            'filters.delivery_via' => ['sometimes', 'nullable', Rule::in(ProjectBroadcastDeliveryEnum::values())],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages());
    }
}
