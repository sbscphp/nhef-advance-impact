<?php

namespace App\Http\Requests\Customer\Networking;

use App\Http\Requests\ApiFormRequest;

class StartDirectConversationRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_uuid' => ['required', 'uuid'],
        ];
    }
}
