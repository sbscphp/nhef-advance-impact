<?php

namespace App\Http\Requests\Admin\Crm;

use App\Enums\ProspectPipelineStageEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class ChangeProspectStageRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'stage' => ['required', Rule::in(ProspectPipelineStageEnum::values())],
        ];
    }
}
