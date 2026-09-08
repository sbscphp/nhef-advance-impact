<?php

namespace App\Http\Controllers\v1\Admin\CustomFields;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomFields\CreateCustomFieldRequest;
use App\Http\Requests\Admin\CustomFields\CustomFieldListRequest;
use App\Http\Requests\Admin\CustomFields\UpdateCustomFieldRequest;
use App\Http\Resources\CustomFields\CustomFieldDefinitionResource;
use App\Models\Admin;
use App\Responser\JsonResponser;
use App\Services\CustomFields\CustomFieldDefinitionService;
use App\Services\DropdownService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomFieldDefinitionController extends Controller
{
    public function __construct(
        private readonly CustomFieldDefinitionService $definitionService,
        private readonly DropdownService $dropdownService,
    ) {}

    public function metadata()
    {
        try {
            return JsonResponser::send(false, 'Custom field metadata retrieved.', $this->dropdownService->customFieldMetadata());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\CustomFields\CustomFieldDefinitionController@metadata');
        }
    }

    public function store(CreateCustomFieldRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $definition = $this->definitionService->create($request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Custom field created.', CustomFieldDefinitionResource::make($definition)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\CustomFields\CustomFieldDefinitionController@store');
        }
    }

    public function index(CustomFieldListRequest $request)
    {
        try {
            $filters = $request->validated();

            return JsonResponser::send(false, 'Custom fields retrieved.', $this->paginatedPayload(
                $this->definitionService->paginateForAdmin($filters),
                CustomFieldDefinitionResource::class
            ));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\CustomFields\CustomFieldDefinitionController@index');
        }
    }

    public function show(string $uuid)
    {
        try {
            $definition = $this->definitionService->findForAdmin($uuid);

            return JsonResponser::send(false, 'Custom field retrieved.', CustomFieldDefinitionResource::make($definition)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\CustomFields\CustomFieldDefinitionController@show');
        }
    }

    public function update(UpdateCustomFieldRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $definition = $this->definitionService->update($uuid, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Custom field updated.', CustomFieldDefinitionResource::make($definition)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\CustomFields\CustomFieldDefinitionController@update');
        }
    }

    public function archive(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $definition = $this->definitionService->archive($uuid, $admin, $request);

            return JsonResponser::send(false, 'Custom field archived.', CustomFieldDefinitionResource::make($definition)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\CustomFields\CustomFieldDefinitionController@archive');
        }
    }

    /**
     * @param  class-string<JsonResource>  $resourceClass
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator, string $resourceClass): array
    {
        $payload = $paginator->toArray();
        /** @var AnonymousResourceCollection $resource */
        $resource = $resourceClass::collection($paginator);
        $payload['data'] = $resource->resolve();

        return $payload;
    }

    private function requireAdmin(Request $request): Admin
    {
        $admin = $request->user();
        if (! $admin instanceof Admin) {
            abort(403, 'Forbidden.');
        }

        return $admin;
    }
}
