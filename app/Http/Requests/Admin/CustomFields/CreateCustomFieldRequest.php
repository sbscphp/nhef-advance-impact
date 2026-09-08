<?php

namespace App\Http\Requests\Admin\CustomFields;

use App\Enums\CustomFieldTypeEnum;
use App\Enums\ModuleEnums;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class CreateCustomFieldRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in(CustomFieldTypeEnum::values())],
            'applicable_modules' => ['required', 'array', 'min:1'],
            'applicable_modules.*' => [Rule::in(ModuleEnums::customFieldModules())],
            'options' => ['required_if:type,'.CustomFieldTypeEnum::DROPDOWN->value.','.CustomFieldTypeEnum::MULTI_SELECT->value, 'string'],
            'is_required' => ['sometimes', 'boolean'],
            'is_searchable' => ['sometimes', 'boolean'],
            'is_filterable' => ['sometimes', 'boolean'],
            'show_in_reports' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'options.required_if' => 'Please provide at least one option for this field type.',
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('type');
            $options = $this->input('options');

            if (! in_array($type, [CustomFieldTypeEnum::DROPDOWN->value, CustomFieldTypeEnum::MULTI_SELECT->value], true) || ! is_string($options)) {
                return;
            }

            $parsed = array_values(array_filter(array_map('trim', explode(';', $options)), fn (string $option): bool => $option !== ''));

            if (count($parsed) < 2) {
                $validator->errors()->add('options', 'Please provide at least two options, separated by ";".');
            }
        });
    }
}
