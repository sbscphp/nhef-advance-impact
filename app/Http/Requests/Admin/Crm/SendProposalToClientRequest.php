<?php

namespace App\Http\Requests\Admin\Crm;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Http\UploadedFile;

class SendProposalToClientRequest extends ApiFormRequest
{
    private const ALLOWED_ATTACHMENT_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg', 'image/jpg', 'image/png',
    ];

    private const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;

    private const MAX_ATTACHMENTS = 5;

    /** Accepts a single semicolon/comma-separated string, not just an array. */
    protected function prepareForValidation(): void
    {
        $recipients = $this->input('recipient_emails');

        if (is_string($recipients)) {
            $this->merge([
                'recipient_emails' => array_values(array_filter(array_map(
                    'trim',
                    preg_split('/[;,]/', $recipients) ?: []
                ), fn ($email) => $email !== '')),
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'message_title' => ['required', 'string', 'max:255'],
            'message_body' => ['required', 'string'],
            // The prospect's own email is always included server-side; these are additional.
            'recipient_emails' => ['sometimes', 'nullable', 'array'],
            'recipient_emails.*' => ['required', 'email'],
            'attachments' => ['sometimes', 'nullable', 'array', 'max:'.self::MAX_ATTACHMENTS],
            'attachments.*' => [$this->attachmentRule()],
        ];
    }

    private function attachmentRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if ($value instanceof UploadedFile) {
                if (! $value->isValid()) {
                    $fail('One of the attachments failed to upload. Please try again.');

                    return;
                }

                if (! in_array($value->getMimeType(), self::ALLOWED_ATTACHMENT_MIME_TYPES, true)) {
                    $fail('Attachments must be a PDF, Word document, JPG, or PNG.');

                    return;
                }

                if ($value->getSize() > self::MAX_ATTACHMENT_BYTES) {
                    $fail('Each attachment must not be larger than 10MB.');
                }

                return;
            }

            // Accepts a base64/data-URI string or an existing http(s) URL (see FileUploadHelper).
            if (! is_string($value)) {
                $fail('Attachments must be an uploaded file, a URL, or a base64-encoded file.');
            }
        };
    }
}
