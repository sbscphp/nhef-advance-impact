<?php

namespace App\Http\Requests\Admin\Events;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class EventListRequest extends ApiFormRequest
{
    /** Mirrors {@see \App\Models\Event::displayStatus()}: persisted + derived scheduled/ongoing/completed statuses. */
    private const DISPLAY_STATUSES = ['draft', 'scheduled', 'ongoing', 'completed', 'cancelled', 'deactivated', 'archived'];

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
            ListingFilterRules::rules(['name']),
            [
                'filters.status' => ['sometimes', 'nullable', Rule::in(self::DISPLAY_STATUSES)],
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages(), [
            'filters.status.in' => 'Status filter is invalid.',
        ]);
    }
}
