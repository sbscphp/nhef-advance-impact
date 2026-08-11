<?php

namespace App\Http\Requests\Customer\Networking;

use App\Enums\NetworkingReactionEmojiEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class ReactionRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'emoji' => ['required', 'string', Rule::in(NetworkingReactionEmojiEnum::values())],
        ];
    }
}
