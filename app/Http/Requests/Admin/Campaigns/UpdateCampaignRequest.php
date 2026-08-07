<?php

namespace App\Http\Requests\Admin\Campaigns;

use App\Enums\CurrencyEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class UpdateCampaignRequest extends ApiFormRequest
{
    private const ALLOWED_COVER_MIME_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

    private const MAX_COVER_BYTES = 10 * 1024 * 1024;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'goal_amount' => ['sometimes', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', Rule::in(CurrencyEnum::values())],
            'allocated_admin_id' => ['sometimes', 'uuid', 'exists:admins,uuid'],
            'bank_account_id' => ['sometimes', 'uuid', 'exists:bank_accounts,uuid'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'description' => ['sometimes', 'string'],
            'cover' => ['sometimes', 'nullable', $this->coverRule()],
        ];
    }

    private function coverRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null) {
                return;
            }

            if (! $value instanceof UploadedFile) {
                $fail('The cover must be an uploaded image.');

                return;
            }

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
        };
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'allocated_admin_id.exists' => 'The selected officer does not exist.',
            'bank_account_id.exists' => 'The selected bank account does not exist.',
        ]);
    }
}
