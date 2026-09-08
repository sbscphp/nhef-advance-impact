<?php

namespace App\Http\Controllers\v1\Customer\CustomFields;

use App\Enums\ModuleEnums;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CustomFields\CustomFieldsForModuleRequest;
use App\Http\Requests\Customer\CustomFields\UpdateMyCustomFieldValuesRequest;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\CustomFields\CustomFieldValueService;
use Illuminate\Http\Request;

class CustomFieldValueController extends Controller
{
    public function __construct(private readonly CustomFieldValueService $valueService) {}

    public function forModule(CustomFieldsForModuleRequest $request)
    {
        try {
            $fields = $this->valueService->fieldsForModule($request->validated()['module']);

            return JsonResponser::send(false, 'Custom fields retrieved.', $fields);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\CustomFields\CustomFieldValueController@forModule');
        }
    }

    public function index(Request $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $fields = $this->valueService->listForFieldable($user, ModuleEnums::constituent_management->value);

            return JsonResponser::send(false, 'Custom fields retrieved.', $fields);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\CustomFields\CustomFieldValueController@index');
        }
    }

    public function update(UpdateMyCustomFieldValuesRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $fields = $this->valueService->updateForFieldable($user, ModuleEnums::constituent_management->value, $request->validated()['values']);

            return JsonResponser::send(false, 'Custom fields updated.', $fields);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\CustomFields\CustomFieldValueController@update');
        }
    }

    private function requireCustomer(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403, 'Forbidden.');
        }

        return $user;
    }
}
