<?php

namespace App\Http\Requests\Admin\Settings;

use App\Http\Requests\ApiFormRequest;

class UpdateWelcomeMessageRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // The viewing admin's own dismissed state; independent of the content fields below.
            'visible' => ['sometimes', 'boolean'],

            'heading' => ['sometimes', 'nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'banner' => ['sometimes', 'nullable'],
            'photo' => ['sometimes', 'nullable'],
            'signature' => ['sometimes', 'nullable'],
            'signer_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'signer_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tagline_emphasis' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
