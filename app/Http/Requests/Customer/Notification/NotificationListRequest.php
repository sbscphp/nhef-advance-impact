<?php

namespace App\Http\Requests\Customer\Notification;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class NotificationListRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(
            // No sortable columns: notifications have neither a "name" nor a "value" to order
            // arrangement by, only the date range and the existing read/unread status.
            ListingFilterRules::rules([]),
            [
                'filters.read_status' => ['sometimes', 'nullable', Rule::in(['all', 'read', 'unread'])],
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages(), [
            'filters.read_status.in' => 'Read status filter must be one of: all, read, unread.',
        ]);
    }
}
