<?php

namespace App\Http\Requests\Admin\Crm;

use App\Enums\ProposalStatusEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateProposalRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string'],
            'save_as' => ['sometimes', Rule::in([ProposalStatusEnum::DRAFT->value, 'save'])],
        ];
    }
}
