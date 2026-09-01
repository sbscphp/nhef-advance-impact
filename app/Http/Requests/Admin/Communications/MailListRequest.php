<?php

namespace App\Http\Requests\Admin\Communications;

use App\Enums\MailStatusEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Validation\Rule;

class MailListRequest extends ApiFormRequest
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
        return array_merge(ListingFilterRules::rules(['created_at']), [
            'filters.status' => ['sometimes', 'nullable', Rule::in(MailStatusEnum::values())],
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
