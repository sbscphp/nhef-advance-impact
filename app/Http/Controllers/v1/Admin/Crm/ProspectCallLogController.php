<?php

namespace App\Http\Controllers\v1\Admin\Crm;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Crm\LogProspectCallRequest;
use App\Http\Requests\Admin\Crm\ProspectRelatedListRequest;
use App\Http\Resources\Crm\ProspectCallLogResource;
use App\Models\Admin;
use App\Responser\JsonResponser;
use App\Services\Crm\ProspectService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class ProspectCallLogController extends Controller
{
    public function __construct(private readonly ProspectService $prospectService) {}

    public function index(ProspectRelatedListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->prospectService->paginateCalls($uuid, $request->validated());

            return JsonResponser::send(false, 'Call logs retrieved.', $this->paginatedPayload($paginator, ProspectCallLogResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectCallLogController@index');
        }
    }

    public function store(LogProspectCallRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $call = $this->prospectService->logCall($uuid, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Call logged successfully.', ProspectCallLogResource::make($call)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectCallLogController@store');
        }
    }

    public function show(string $uuid, string $callUuid)
    {
        try {
            $call = $this->prospectService->findCall($uuid, $callUuid);

            return JsonResponser::send(false, 'Call log retrieved.', ProspectCallLogResource::make($call)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectCallLogController@show');
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
