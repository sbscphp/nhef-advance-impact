<?php

namespace App\Http\Requests\Admin\CustomFields;

use App\Enums\CustomFieldStatusEnum;
use App\Enums\ModuleEnums;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class CustomFieldListRequest extends ApiFormRequest
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
            ListingFilterRules::rules(['name', 'created_at']),
            [
                'filters.status' => ['sometimes', 'nullable', Rule::in(CustomFieldStatusEnum::values())],
                'filters.applicable_module' => ['sometimes', 'nullable', Rule::in(ModuleEnums::customFieldModules())],
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages(), [
            'filters.status.in' => 'Status filter is invalid.',
            'filters.applicable_module.in' => 'Applicable module filter is invalid.',
        ]);
    }
}
