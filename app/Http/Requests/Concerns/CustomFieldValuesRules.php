<?php

namespace App\Http\Requests\Concerns;

final class CustomFieldValuesRules
{
    /**
     * Optional "custom_field_values" array on a record-creation request; per-value shape is
     * validated in CustomFieldValueService, not here.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'custom_field_values' => ['sometimes', 'array'],
            'custom_field_values.*.custom_field_uuid' => ['required', 'uuid'],
            'custom_field_values.*.value' => ['nullable'],
        ];
    }
}
