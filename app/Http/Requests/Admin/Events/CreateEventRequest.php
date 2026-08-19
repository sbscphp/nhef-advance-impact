<?php

namespace App\Http\Requests\Admin\Events;

use App\Enums\CurrencyEnum;
use App\Enums\EventTypeEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class CreateEventRequest extends ApiFormRequest
{
    private const ALLOWED_BANNER_MIME_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    private const MAX_BANNER_BYTES = 5 * 1024 * 1024;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'registration_ends_at' => ['sometimes', 'nullable', 'date', 'before_or_equal:starts_at'],
            'banner_image' => ['required', $this->bannerRule()],
            'event_type' => ['required', Rule::in(EventTypeEnum::values())],
            'virtual_link' => ['required_if:event_type,'.EventTypeEnum::VIRTUAL->value, 'nullable', 'url'],
            'venue_name' => ['required_if:event_type,'.EventTypeEnum::PHYSICAL->value, 'nullable', 'string', 'max:255'],
            'venue_address' => ['required_if:event_type,'.EventTypeEnum::PHYSICAL->value, 'nullable', 'string', 'max:255'],
            'waitlist_enabled' => ['sometimes', 'boolean'],
            'is_paid' => ['required', 'boolean'],
            'tickets' => ['required', 'array', 'min:1'],
            'tickets.*.uuid' => ['sometimes', 'nullable', 'uuid'],
            'tickets.*.name' => ['required', 'string', 'max:255'],
            'tickets.*.price' => [Rule::requiredIf(fn () => $this->boolean('is_paid')), 'nullable', 'numeric', 'min:0'],
            'tickets.*.currency' => ['sometimes', 'nullable', Rule::in(CurrencyEnum::values())],
            'tickets.*.quantity' => ['required', 'integer', 'min:1'],
            'tickets.*.discount_percentage' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'tickets.*.discount_starts_at' => ['sometimes', 'nullable', 'date'],
            'tickets.*.discount_ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:tickets.*.discount_starts_at'],
        ];
    }

    private function bannerRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value instanceof UploadedFile) {
                if (! $value->isValid()) {
                    $fail('The banner upload failed. Please try again.');

                    return;
                }

                if (! in_array($value->getMimeType(), self::ALLOWED_BANNER_MIME_TYPES, true)) {
                    $fail('The banner must be a JPG, PNG, or WEBP image.');

                    return;
                }

                if ($value->getSize() > self::MAX_BANNER_BYTES) {
                    $fail('The banner must not be larger than 5MB.');
                }

                return;
            }

            // Accepts a base64/data-URI string or an existing http(s) URL (see FileUploadHelper).
            if (! is_string($value) || trim($value) === '') {
                $fail('The banner must be an uploaded image, a URL, or a base64-encoded image.');
            }
        };
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'event_type.required' => 'Please select whether the event is online or physical.',
            'virtual_link.required_if' => 'Please provide a virtual meeting link.',
            'venue_name.required_if' => 'Please provide a venue name.',
            'venue_address.required_if' => 'Please provide a venue address.',
            'tickets.required' => 'Please add at least one ticket type.',
            'tickets.*.price.required_if' => 'Please provide a ticket price.',
        ]);
    }
}
