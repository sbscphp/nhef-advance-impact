<?php

namespace App\Http\Requests\Admin\ConstituentManagement;

use App\Http\Requests\ApiFormRequest;

class ConferConstituencyTypeRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'constituency_type_ids' => ['required', 'array', 'min:1'],
            'constituency_type_ids.*' => ['required', 'uuid', 'exists:constituency_types,uuid'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'constituency_type_ids.required' => 'Please select at least one constituency type.',
            'constituency_type_ids.min' => 'Please select at least one constituency type.',
            'constituency_type_ids.*.exists' => 'One of the selected constituency types does not exist.',
        ]);
    }
}
