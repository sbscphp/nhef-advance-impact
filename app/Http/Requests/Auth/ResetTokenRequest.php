<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;

class ResetTokenRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'token' => 'reset token',
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'token.required' => 'The reset token is required.',
        ]);
    }
}
