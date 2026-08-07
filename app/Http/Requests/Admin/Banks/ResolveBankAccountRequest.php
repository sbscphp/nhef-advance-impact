<?php

namespace App\Http\Requests\Admin\Banks;

use App\Http\Requests\ApiFormRequest;

class ResolveBankAccountRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'bank_id' => ['required', 'uuid', 'exists:banks,uuid'],
            'account_number' => ['required', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'bank_id.required' => 'Please select a bank.',
            'bank_id.exists' => 'The selected bank does not exist.',
        ]);
    }
}
