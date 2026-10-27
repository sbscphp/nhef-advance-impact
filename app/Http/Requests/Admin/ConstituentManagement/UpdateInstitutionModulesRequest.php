<?php

namespace App\Http\Requests\Admin\ConstituentManagement;

use App\Enums\ModuleEnums;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Sets an existing institution's "System Access Allowable" grant - the same `modules` list
 * InviteInstitutionRequest accepts at invite time, just reachable afterward too (e.g. for an
 * institution that predates this feature and was never granted anything).
 */
class UpdateInstitutionModulesRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'modules' => ['required', 'array'],
            'modules.*' => ['required', 'string', Rule::in(ModuleEnums::institutionModuleKeys())],
        ];
    }
}
