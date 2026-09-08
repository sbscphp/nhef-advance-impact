<?php

namespace App\Http\Requests\Customer\CustomFields;

use App\Enums\ModuleEnums;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class CustomFieldsForModuleRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'module' => ['required', Rule::in(ModuleEnums::customerCustomFieldModules())],
        ];
    }
}
