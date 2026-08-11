<?php

namespace App\Http\Requests\Customer\Networking;

use App\Http\Requests\ApiFormRequest;

class SendMessageRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required_without:attachment', 'nullable', 'string', 'max:5000'],
            'attachment' => [
                'required_without:body',
                'nullable',
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,gif,webp,svg,pdf,doc,docx,txt',
            ],
        ];
    }
}
