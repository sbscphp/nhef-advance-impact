<?php

namespace App\Http\Requests\Admin\Crm;

use App\Http\Requests\ApiFormRequest;

class CreateProposalRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
