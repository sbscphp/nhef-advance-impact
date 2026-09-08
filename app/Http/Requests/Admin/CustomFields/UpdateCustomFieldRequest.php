<?php

namespace App\Http\Requests\Admin\CustomFields;

use App\Enums\ModuleEnums;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class UpdateCustomFieldRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'applicable_modules' => ['sometimes', 'array', 'min:1'],
            'applicable_modules.*' => [Rule::in(ModuleEnums::customFieldModules())],
            'options' => ['sometimes', 'string'],
            'is_required' => ['sometimes', 'boolean'],
            'is_searchable' => ['sometimes', 'boolean'],
            'is_filterable' => ['sometimes', 'boolean'],
            'show_in_reports' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $options = $this->input('options');

            if (! is_string($options)) {
                return;
            }

            $parsed = array_values(array_filter(array_map('trim', explode(';', $options)), fn (string $option): bool => $option !== ''));

            if (count($parsed) < 2) {
                $validator->errors()->add('options', 'Please provide at least two options, separated by ";".');
            }
        });
    }
}
