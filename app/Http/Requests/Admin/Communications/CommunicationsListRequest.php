<?php

namespace App\Http\Requests\Admin\Communications;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

/**
 * Shared listing rules for the Mails/Call Logs/Tasks index endpoints. `export` is only acted
 * on by the endpoints that actually offer it (currently Unsubscribers); harmless elsewhere.
 */
class CommunicationsListRequest extends ApiFormRequest
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
        return array_merge(ListingFilterRules::rules(['created_at', 'due_date', 'call_date']), [
            'export' => ['sometimes', 'nullable', Rule::in(['csv', 'pdf'])],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages(), [
            'export.in' => "Export format must be either 'csv' or 'pdf'.",
        ]);
    }
}
