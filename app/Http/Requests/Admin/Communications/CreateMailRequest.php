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
            'segment.tertiary_institution_uuid' => ['sometimes', 'nullable', 'uuid', 'exists:tertiary_institutions,uuid'],
            'segment.department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'segment.graduation_year_from' => ['sometimes', 'nullable', 'integer', 'digits:4'],
            'segment.graduation_year_to' => ['sometimes', 'nullable', 'integer', 'digits:4', 'gte:segment.graduation_year_from'],
            'recipient_user_ids' => ['sometimes', 'array'],
            'recipient_user_ids.*' => ['uuid', 'exists:users,uuid'],
            // "Other Recipient": pasted emails, matched back against real constituents in
            // MailService where possible (no `exists` here, since a non-constituent email is
            // equally valid).
            'recipient_emails' => ['sometimes', 'array'],
            'recipient_emails.*' => ['email'],
        ];
    }
}
