<?php

namespace App\Http\Requests\Customer\Networking;

use App\Enums\NetworkingChannelTypeEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

/**
 * Shared by both "My conversations" and "Browse Community/Forum channels". sort_by,
 * sort_direction, period, start_date, and end_date only take effect on the browse endpoint;
 * conversations always stay ordered by most recent activity, since a direct conversation has no
 * queryable name or creation date of its own.
 */
class ChannelListRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        ListingFilterRules::applyPeriodDateRangeToRequest($this);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(
            ListingFilterRules::rules(['name', 'created_at', 'members_count']),
            [
                'type' => ['sometimes', Rule::in(NetworkingChannelTypeEnum::values())],
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages());
    }
}
