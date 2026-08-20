<?php

namespace App\Http\Requests\Admin\ConstituentManagement;

use App\Enums\ConstituentStatusEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateConstituentRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $userUuid = $this->route('uuid');

        return [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userUuid, 'uuid')],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'invite_message' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::in(ConstituentStatusEnum::values())],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'email.unique' => 'A constituent with this email already exists.',
        ]);
    }
}
