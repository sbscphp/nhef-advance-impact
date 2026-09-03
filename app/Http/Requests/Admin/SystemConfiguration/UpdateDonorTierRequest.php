<?php

namespace App\Http\Requests\Admin\SystemConfiguration;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class UpdateDonorTierRequest extends ApiFormRequest
{
    private const ALLOWED_BADGE_MIME_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    private const MAX_BADGE_BYTES = 1 * 1024 * 1024;

    private const BADGE_DIMENSION_PX = 512;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tierUuid = $this->route('uuid');

        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('donor_tiers', 'name')->ignore($tierUuid, 'uuid')],
            'minimum_amount' => ['sometimes', 'numeric', 'min:0'],
            'maximum_amount' => ['sometimes', 'numeric', 'gt:minimum_amount'],
            'badge' => ['sometimes', 'nullable', $this->badgeRule()],
        ];
    }

    private function badgeRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if ($value instanceof UploadedFile) {
                if (! $value->isValid()) {
                    $fail('The badge upload failed. Please try again.');

                    return;
                }

                if (! in_array($value->getMimeType(), self::ALLOWED_BADGE_MIME_TYPES, true)) {
                    $fail('The badge must be a JPG, PNG, or WEBP image.');

                    return;
                }

                if ($value->getSize() > self::MAX_BADGE_BYTES) {
                    $fail('The badge must not be larger than 1MB.');

                    return;
                }

                $dimensions = @getimagesize($value->getRealPath());
                if ($dimensions === false || $dimensions[0] !== self::BADGE_DIMENSION_PX || $dimensions[1] !== self::BADGE_DIMENSION_PX) {
                    $fail('The badge must be exactly 512x512 pixels.');
                }

                return;
            }

            if (! is_string($value)) {
                $fail('The badge must be an uploaded image, a URL, or a base64-encoded image.');
            }
        };
    }
}
