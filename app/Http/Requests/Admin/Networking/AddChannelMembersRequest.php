<?php

namespace App\Http\Requests\Admin\Networking;

use App\Http\Requests\ApiFormRequest;

/** Backs "+ Add People" on the Members tab, and the initial member picker in Add New Community/Forum. */
class AddChannelMembersRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'member_uuids' => ['required', 'array', 'min:1'],
            'member_uuids.*' => ['uuid', 'exists:users,uuid'],
        ];
    }
}
