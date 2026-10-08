<?php

namespace App\Http\Requests\Admin\ConstituentManagement;

use App\Enums\ConstituentTypeEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class InviteConstituentRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'constituent_type' => ['sometimes', Rule::in(ConstituentTypeEnum::values())],
            'invite_message' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'email.unique' => 'A constituent with this email already exists.',
        ]);
    }
}
