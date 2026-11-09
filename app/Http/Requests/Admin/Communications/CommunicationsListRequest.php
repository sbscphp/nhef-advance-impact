<?php

namespace App\Http\Requests\Admin\Communications;

use App\Enums\CommunicationLogTypeEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

/**
 * Shared listing rules for the Mails/Call Logs/Tasks index endpoints. `export` and `mail_uuid`
 * are only acted on by Unsubscribers, `log_type` only by Call Logs; harmless elsewhere.
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
            'mail_uuid' => ['sometimes', 'nullable', 'uuid', Rule::exists('mails', 'uuid')],
            'log_type' => ['sometimes', 'nullable', Rule::in(CommunicationLogTypeEnum::values())],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages(), [
            'export.in' => "Export format must be either 'csv' or 'pdf'.",
        ]);
    }
}
