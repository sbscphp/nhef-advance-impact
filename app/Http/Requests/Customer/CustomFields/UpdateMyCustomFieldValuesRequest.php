<?php

namespace App\Http\Requests\Customer\CustomFields;

use App\Http\Requests\ApiFormRequest;

class UpdateMyCustomFieldValuesRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'values' => ['required', 'array', 'min:1'],
            'values.*.custom_field_uuid' => ['required', 'uuid'],
            // Shape varies per field type; deep validation happens in CustomFieldValueService.
            'values.*.value' => ['nullable'],
        ];
    }
}
