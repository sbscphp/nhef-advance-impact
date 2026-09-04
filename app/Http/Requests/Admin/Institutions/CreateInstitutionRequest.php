<?php

namespace App\Http\Requests\Admin\Institutions;

use App\Http\Requests\ApiFormRequest;

class CreateInstitutionRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tertiary_institution_uuid' => ['required', 'uuid', 'exists:tertiary_institutions,uuid'],
        ];
    }
}
