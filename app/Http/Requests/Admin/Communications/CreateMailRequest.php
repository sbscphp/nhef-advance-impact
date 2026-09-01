<?php

namespace App\Http\Requests\Admin\Communications;

use App\Http\Requests\ApiFormRequest;

class CreateMailRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'banner' => ['sometimes', 'nullable'],
            'body' => ['required', 'string'],
            'send_at' => ['sometimes', 'nullable', 'date'],
            'segment' => ['sometimes', 'nullable', 'array'],
            'segment.university' => ['sometimes', 'nullable', 'string', 'max:255'],
            'segment.department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'segment.graduation_year_from' => ['sometimes', 'nullable', 'integer', 'digits:4'],
            'segment.graduation_year_to' => ['sometimes', 'nullable', 'integer', 'digits:4', 'gte:segment.graduation_year_from'],
            'recipient_user_ids' => ['sometimes', 'array'],
            'recipient_user_ids.*' => ['uuid', 'exists:users,uuid'],
        ];
    }
}
