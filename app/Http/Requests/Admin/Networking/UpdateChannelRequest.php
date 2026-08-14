<?php

namespace App\Http\Requests\Admin\Networking;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Http\UploadedFile;

/** Backs the "About" tab of Community/Forum Details: name, description, avatar only. */
class UpdateChannelRequest extends ApiFormRequest
{
    private const ALLOWED_AVATAR_MIME_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

    private const MAX_AVATAR_BYTES = 5 * 1024 * 1024;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:50'],
            // Accepts an uploaded image file, an existing http(s) URL, or a base64/data-URI
            // string (see FileUploadHelper and NetworkingService::updateChannelForAdmin()).
            'avatar' => ['sometimes', 'nullable', $this->avatarRule()],
        ];
    }

    private function avatarRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if ($value instanceof UploadedFile) {
                if (! $value->isValid()) {
                    $fail('The avatar upload failed. Please try again.');

                    return;
                }

                if (! in_array($value->getMimeType(), self::ALLOWED_AVATAR_MIME_TYPES, true)) {
                    $fail('The avatar must be a JPG, PNG, GIF, or WEBP image.');

                    return;
                }

                if ($value->getSize() > self::MAX_AVATAR_BYTES) {
                    $fail('The avatar must not be larger than 5MB.');
                }

                return;
            }

            if (! is_string($value)) {
                $fail('The avatar must be an uploaded image, a URL, or a base64-encoded image.');
            }
        };
    }
}
