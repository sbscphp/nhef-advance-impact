<?php

namespace App\Http\Requests\Admin\Networking;

use App\Enums\NetworkingChannelTypeEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/** Backs both "Add New Community" and "Add New Forum"; the two Figma flows differ only by `type`. */
class CreateChannelRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(NetworkingChannelTypeEnum::browsableValues())],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:50'],
            'avatar' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'member_uuids' => ['sometimes', 'array'],
            'member_uuids.*' => ['uuid', 'exists:users,uuid'],
        ];
    }
}
