<?php

namespace App\Http\Controllers\v1\Admin\Crm;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Crm\ChangeProspectStageRequest;
use App\Http\Requests\Admin\Crm\CreateProspectRequest;
use App\Http\Requests\Admin\Crm\ProspectListRequest;
use App\Http\Requests\Admin\Crm\UpdateProspectRequest;
use App\Http\Resources\Crm\ProspectListResource;
use App\Http\Resources\Crm\ProspectResource;
use App\Models\Admin;
use App\Responser\JsonResponser;
use App\Services\Crm\ProspectService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class ProspectController extends Controller
{
    public function __construct(private readonly ProspectService $prospectService) {}

    /** Grouped by pipeline stage, for the CRM Kanban board (not a flat list; see list()). */
    public function index()
    {
        try {
            $board = $this->prospectService->kanban()
                ->map(fn ($prospects) => ProspectListResource::collection($prospects)->resolve());

            return JsonResponser::send(false, 'Prospect pipeline retrieved.', $board);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectController@index');
        }
    }

    public function list(ProspectListRequest $request)
    {
        try {
            $paginator = $this->prospectService->paginateForAdmin($request->validated());

            return JsonResponser::send(false, 'Prospects retrieved.', $this->paginatedPayload($paginator, ProspectResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectController@list');
        }
    }

    public function store(CreateProspectRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $prospect = $this->prospectService->create($request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Prospect added successfully.', ProspectResource::make($prospect)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectController@store');
        }
    }

    public function show(string $uuid)
    {
        try {
            $prospect = $this->prospectService->findForAdmin($uuid);

            return JsonResponser::send(false, 'Prospect retrieved.', ProspectResource::make($prospect)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectController@show');
        }
    }

    public function update(UpdateProspectRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $prospect = $this->prospectService->update($uuid, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Prospect updated successfully.', ProspectResource::make($prospect)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectController@update');
        }
    }

    public function changeStage(ChangeProspectStageRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $prospect = $this->prospectService->changeStage($uuid, $request->validated('stage'), $admin, $request);

            return JsonResponser::send(false, 'Prospect stage updated.', ProspectResource::make($prospect)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectController@changeStage');
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
