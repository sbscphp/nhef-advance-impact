<?php

namespace App\Http\Requests\Customer\Settings;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Http\UploadedFile;

class CustomerProfileUpdateRequest extends ApiFormRequest
{
    private const ALLOWED_PICTURE_MIME_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

    private const MAX_PICTURE_BYTES = 10 * 1024 * 1024;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'firstname' => ['sometimes', 'string', 'max:255'],
            'lastname' => ['sometimes', 'string', 'max:255'],
            'middlename' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'country_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'university' => ['sometimes', 'string', 'max:255'],
            'year_of_graduation' => ['sometimes', 'integer', 'min:1960', 'max:'.now()->year],
            'email' => ['prohibited'],
            // Accepts an uploaded image file, an existing http(s) URL, a base64/data-URI
            // string, or null to remove the current picture — see FileUploadHelper and
            // AccountSettingsService::updateCustomerProfile().
            'profile_picture' => ['sometimes', 'nullable', $this->profilePictureRule()],
        ];
    }

    private function profilePictureRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            // Empty string is treated the same as null — "remove the current picture" (see
            // AccountSettingsService::updateCustomerProfile()).
            if ($value === null || $value === '') {
                return;
            }

            if ($value instanceof UploadedFile) {
                if (! $value->isValid()) {
                    $fail('The profile picture upload failed. Please try again.');

                    return;
                }

                if (! in_array($value->getMimeType(), self::ALLOWED_PICTURE_MIME_TYPES, true)) {
                    $fail('The profile picture must be a JPG, PNG, GIF, or WEBP image.');

                    return;
                }

                if ($value->getSize() > self::MAX_PICTURE_BYTES) {
                    $fail('The profile picture must not be larger than 10MB.');
                }

                return;
            }

            if (! is_string($value)) {
                $fail('The profile picture must be an uploaded image, a URL, or a base64-encoded image.');
            }
        };
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'email.prohibited' => 'Email cannot be changed.',
        ]);
    }
}
