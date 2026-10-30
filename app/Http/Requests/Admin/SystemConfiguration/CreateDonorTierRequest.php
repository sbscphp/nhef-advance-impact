<?php

namespace App\Http\Requests\Admin\SystemConfiguration;

use App\Enums\AdminScopeEnum;
use App\Http\Requests\ApiFormRequest;
use App\Models\Admin;
use App\Models\Institution;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class CreateDonorTierRequest extends ApiFormRequest
{
    private const ALLOWED_BADGE_MIME_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    private const MAX_BADGE_BYTES = 1 * 1024 * 1024;

    private const BADGE_DIMENSION_PX = 512;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $actor = $this->user();
        $isInstitutionScope = $actor instanceof Admin && AdminScopeEnum::of($actor) === AdminScopeEnum::INSTITUTION;

        // An institution admin's tier always belongs to their own institution, so it's optional
        // here, not required; NHEF alone must pick one. Tier names are unique per institution.
        $institutionId = $isInstitutionScope
            ? $actor->institution_id
            : Institution::query()->where('uuid', (string) $this->input('institution_uuid'))->value('id');

        return [
            'institution_uuid' => [$isInstitutionScope ? 'sometimes' : 'required', 'uuid', 'exists:institutions,uuid'],
            'name' => ['required', 'string', 'max:255', Rule::unique('donor_tiers', 'name')->where('institution_id', $institutionId)],
            'minimum_amount' => ['required', 'numeric', 'min:0'],
            'maximum_amount' => ['required', 'numeric', 'gt:minimum_amount'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'badge' => ['required', $this->badgeRule()],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'institution_uuid.required' => 'Please select which institution this tier belongs to.',
            'institution_uuid.exists' => 'The selected institution does not exist.',
            'name.unique' => 'This institution already has a tier with this name.',
            'color.regex' => 'Color must be a hex code, e.g. #C0C0C0.',
        ]);
    }

    private function badgeRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
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

            // Accepts a base64/data-URI string or an existing http(s) URL (see FileUploadHelper);
            // dimensions aren't re-checked for these, matching how other upload fields behave.
            if (! is_string($value) || trim($value) === '') {
                $fail('The badge must be an uploaded image, a URL, or a base64-encoded image.');
            }
        };
    }
}
