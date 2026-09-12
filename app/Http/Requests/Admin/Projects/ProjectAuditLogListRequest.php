<?php

namespace App\Http\Requests\Admin\Projects;

use App\Enums\AuditActionEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Services\Audit\AuditTrailQueryService;
use Illuminate\Validation\Rule;

class ProjectAuditLogListRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(ListingFilterRules::rules(AuditTrailQueryService::SORTABLE_COLUMNS), [
            'filters.action' => ['sometimes', 'nullable', Rule::in(AuditActionEnum::values())],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ListingFilterRules::listingMessages(), [
            'filters.action.in' => 'Action filter is invalid.',
        ]);
    }

    protected function prepareForValidation(): void
    {
        ListingFilterRules::applyPeriodDateRangeToRequest($this);
    }
}
