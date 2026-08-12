<?php

namespace App\Http\Requests\Admin\Institutions;

use App\Http\Requests\ApiFormRequest;

class CreateInstitutionRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:institutions,name'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'name.unique' => 'An institution with this name already exists.',
        ]);
    }
}
