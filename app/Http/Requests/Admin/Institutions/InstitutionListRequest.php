<?php

namespace App\Http\Requests\Admin\Institutions;

use App\Http\Requests\ApiFormRequest;

class InstitutionListRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'active_only' => ['sometimes', 'boolean'],
        ];
    }
}
