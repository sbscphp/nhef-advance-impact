<?php

namespace App\Http\Requests\Admin\Crm;

use App\Enums\ProspectPipelineStageEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class ProspectListRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        ListingFilterRules::applyPeriodDateRangeToRequest($this);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(
            ListingFilterRules::rules(['name', 'value', 'created_at']),
            [
                'filters.stage' => ['sometimes', 'nullable', Rule::in(ProspectPipelineStageEnum::values())],
                'filters.assigned_admin_id' => ['sometimes', 'nullable', 'uuid'],
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages(), [
            'filters.stage.in' => 'Stage filter is invalid.',
        ]);
    }
}
