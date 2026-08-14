<?php

namespace App\Http\Requests\Admin\Campaigns;

use App\Enums\CurrencyEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class CreateNationalGivingDayCampaignRequest extends ApiFormRequest
{
    private const ALLOWED_COVER_MIME_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

    private const MAX_COVER_BYTES = 10 * 1024 * 1024;

    /** Fallback for clients sending `institutions` as a JSON-encoded string instead of nested fields. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('institutions'))) {
            $decoded = json_decode((string) $this->input('institutions'), true);
            if (is_array($decoded)) {
                $this->merge(['institutions' => $decoded]);
            }
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'allocated_admin_id' => ['required', 'uuid', 'exists:admins,uuid'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'description' => ['required', 'string'],
            'cover' => ['required', $this->coverRule()],
            'institutions' => ['required', 'array', 'min:1'],
            'institutions.*.institution_id' => ['required', 'uuid', 'exists:institutions,uuid'],
            'institutions.*.goal_amount' => ['required', 'numeric', 'min:0.01'],
            'institutions.*.currency' => ['required', Rule::in(CurrencyEnum::values())],
            'institutions.*.bank_account_id' => ['required', 'uuid', 'exists:bank_accounts,uuid'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'institutions.required' => 'Please add at least one institution.',
            'institutions.min' => 'Please add at least one institution.',
            'institutions.*.institution_id.required' => 'Please select an institution.',
            'institutions.*.institution_id.exists' => 'The selected institution does not exist.',
            'institutions.*.goal_amount.required' => 'Please provide a goal for this institution.',
            'institutions.*.bank_account_id.required' => 'Please select or add a bank account for this institution.',
            'institutions.*.bank_account_id.exists' => 'The selected bank account does not exist.',
        ]);
    }

    private function coverRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value instanceof UploadedFile) {
                if (! $value->isValid()) {
                    $fail('The cover upload failed. Please try again.');

                    return;
                }

                if (! in_array($value->getMimeType(), self::ALLOWED_COVER_MIME_TYPES, true)) {
                    $fail('The cover must be a JPG, PNG, GIF, or WEBP image.');

                    return;
                }

                if ($value->getSize() > self::MAX_COVER_BYTES) {
                    $fail('The cover must not be larger than 10MB.');
                }

                return;
            }

            // Accepts a base64/data-URI string or an existing http(s) URL (see FileUploadHelper).
            if (! is_string($value) || trim($value) === '') {
                $fail('The cover must be an uploaded image, a URL, or a base64-encoded image.');
            }
        };
    }
}
