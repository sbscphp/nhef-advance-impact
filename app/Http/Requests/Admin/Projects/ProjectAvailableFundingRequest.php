<?php

namespace App\Http\Requests\Admin\Projects;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Validator;

class ProjectAvailableFundingRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'donor_uuids' => ['sometimes', 'array'],
            'donor_uuids.*' => ['uuid', 'exists:users,uuid'],
            'campaign_uuids' => ['sometimes', 'array'],
            'campaign_uuids.*' => ['uuid', 'exists:campaigns,uuid'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (($this->input('donor_uuids') ?? []) === [] && ($this->input('campaign_uuids') ?? []) === []) {
                $validator->errors()->add('donor_uuids', 'Select at least one donor or campaign.');
            }
        });
    }
}
