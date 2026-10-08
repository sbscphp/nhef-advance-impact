<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Http\UploadedFile;

class UpdateAdminProfileRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'profile_picture' => ['sometimes', 'nullable', $this->pictureRule()],
        ];
    }

    private function pictureRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if ($value instanceof UploadedFile) {
                if (! $value->isValid() || ! in_array($value->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
                    $fail('The profile picture must be a JPG, PNG, or WEBP image.');
                } elseif ($value->getSize() > 2 * 1024 * 1024) {
                    $fail('The profile picture must not be larger than 2MB.');
                }

                return;
            }

            // A URL or base64 image string (see FileUploadHelper).
            if (! is_string($value)) {
                $fail('The profile picture must be an uploaded image, a URL, or a base64-encoded image.');
            }
        };
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'name.required' => 'Please enter your name.',
        ]);
    }
}
