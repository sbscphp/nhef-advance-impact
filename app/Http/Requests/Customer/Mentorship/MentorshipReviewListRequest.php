<?php

namespace App\Http\Requests\Customer\Mentorship;

use App\Http\Requests\ApiFormRequest;

class MentorshipReviewListRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
