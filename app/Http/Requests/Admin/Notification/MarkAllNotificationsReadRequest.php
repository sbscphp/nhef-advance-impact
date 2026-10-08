<?php

namespace App\Http\Requests\Admin\Notification;

use App\Enums\NotificationCategoryEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class MarkAllNotificationsReadRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'category' => ['sometimes', 'nullable', Rule::in(NotificationCategoryEnum::values())],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'category.in' => 'Category filter is invalid.',
        ]);
    }
}
