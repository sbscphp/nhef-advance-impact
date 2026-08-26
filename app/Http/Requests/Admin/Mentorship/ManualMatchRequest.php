<?php

namespace App\Http\Requests\Admin\Mentorship;

use App\Http\Requests\ApiFormRequest;

class ManualMatchRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'mentor_uuid' => ['required', 'uuid'],
            'mentee_uuid' => ['required', 'uuid'],
        ];
    }
}
