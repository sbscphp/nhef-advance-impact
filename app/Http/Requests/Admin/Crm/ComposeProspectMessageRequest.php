<?php

namespace App\Http\Requests\Admin\Crm;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Http\UploadedFile;

class ComposeProspectMessageRequest extends ApiFormRequest
{
    private const ALLOWED_BANNER_MIME_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

    private const MAX_BANNER_BYTES = 10 * 1024 * 1024;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'send_date' => ['required', 'date'],
            'body' => ['required', 'string'],
            'banner' => ['sometimes', 'nullable', $this->bannerRule()],
        ];
    }

    private function bannerRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if ($value instanceof UploadedFile) {
                if (! $value->isValid()) {
                    $fail('The banner upload failed. Please try again.');

                    return;
                }

                if (! in_array($value->getMimeType(), self::ALLOWED_BANNER_MIME_TYPES, true)) {
                    $fail('The banner must be a JPG, PNG, GIF, or WEBP image.');

                    return;
                }

                if ($value->getSize() > self::MAX_BANNER_BYTES) {
                    $fail('The banner must not be larger than 10MB.');
                }

                return;
            }

            // Accepts a base64/data-URI string or an existing http(s) URL (see FileUploadHelper).
            if (! is_string($value)) {
                $fail('The banner must be an uploaded image, a URL, or a base64-encoded image.');
            }
        };
    }
}
