<?php

namespace App\Http\Requests\Admin\Communications;

use App\Enums\MailRecipientStatusEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class MailRecipientListRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(ListingFilterRules::rules(['created_at']), [
            'filters.status' => ['sometimes', 'nullable', Rule::in(MailRecipientStatusEnum::values())],
            'export' => ['sometimes', 'nullable', Rule::in(['csv', 'pdf'])],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages(), [
            'filters.status.in' => 'Status filter is invalid.',
            'export.in' => "Export format must be either 'csv' or 'pdf'.",
        ]);
    }
}
