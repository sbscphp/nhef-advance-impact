<?php

namespace App\Http\Requests\Admin\Mentorship;

use App\Http\Requests\ApiFormRequest;

class SuspendMentorRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
