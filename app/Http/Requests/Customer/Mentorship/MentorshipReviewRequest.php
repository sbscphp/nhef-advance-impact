<?php

namespace App\Http\Requests\Customer\Mentorship;

use App\Http\Requests\ApiFormRequest;

class MentorshipReviewRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'quality_rating' => ['required', 'integer', 'between:1,5'],
            'communication_rating' => ['required', 'integer', 'between:1,5'],
            'responsiveness_rating' => ['required', 'integer', 'between:1,5'],
            'professionalism_rating' => ['required', 'integer', 'between:1,5'],
            'description' => ['required', 'string', 'max:2000'],
        ];
    }
}
