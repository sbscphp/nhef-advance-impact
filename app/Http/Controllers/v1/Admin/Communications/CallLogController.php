<?php

namespace App\Http\Controllers\v1\Admin\Communications;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Communications\AddCallFollowUpTaskRequest;
use App\Http\Requests\Admin\Communications\CommunicationsListRequest;
use App\Http\Requests\Admin\Communications\LogCallRequest;
use App\Http\Resources\Communications\CommunicationCallLogResource;
use App\Models\Admin;
use App\Responser\JsonResponser;
use App\Services\Communications\CallLogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

/** Logged against general constituents (`users`), not CRM's `Prospect`. Overview counts roll up follow-up tasks, not call logs, since a call log has no due date. */
class CallLogController extends Controller
{
    public function __construct(private readonly CallLogService $callLogService) {}

    public function index(CommunicationsListRequest $request)
    {
        try {
            $paginator = $this->callLogService->paginate($request->validated());

            return JsonResponser::send(false, 'Call logs retrieved.', $this->paginatedPayload($paginator, CommunicationCallLogResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\CallLogController@index');
        }
    }

    public function overview()
    {
        try {
            return JsonResponser::send(false, 'Call log overview retrieved.', $this->callLogService->overview());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\CallLogController@overview');
        }
    }

    public function store(LogCallRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $call = $this->callLogService->create($request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Call logged successfully.', CommunicationCallLogResource::make($call)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\CallLogController@store');
        }
    }

    public function show(string $uuid)
    {
        try {
            $call = $this->callLogService->find($uuid);

            return JsonResponser::send(false, 'Call log retrieved.', CommunicationCallLogResource::make($call)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\CallLogController@show');
        }
    }

    public function addTask(AddCallFollowUpTaskRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $call = $this->callLogService->addTask($uuid, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Follow-up task added successfully.', CommunicationCallLogResource::make($call)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\CallLogController@addTask');
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
