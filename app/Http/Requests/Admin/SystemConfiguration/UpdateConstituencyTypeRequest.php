<?php

namespace App\Http\Requests\Admin\SystemConfiguration;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateConstituencyTypeRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $typeUuid = $this->route('uuid');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('constituency_types', 'name')->ignore($typeUuid, 'uuid')],
        ];
    }
}
