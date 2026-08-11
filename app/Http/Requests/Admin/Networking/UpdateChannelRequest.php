<?php

namespace App\Http\Requests\Admin\Networking;

use App\Http\Requests\ApiFormRequest;

/** Backs the "About" tab of Community/Forum Details: name, description, avatar only. */
class UpdateChannelRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:50'],
            'avatar' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
        ];
    }
}
