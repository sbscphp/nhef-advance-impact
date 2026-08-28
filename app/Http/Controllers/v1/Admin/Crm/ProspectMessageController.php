<?php

namespace App\Http\Controllers\v1\Admin\Crm;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Crm\ComposeProspectMessageRequest;
use App\Http\Requests\Admin\Crm\ProspectRelatedListRequest;
use App\Http\Resources\Crm\ProspectMessageResource;
use App\Models\Admin;
use App\Responser\JsonResponser;
use App\Services\Crm\ProspectService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class ProspectMessageController extends Controller
{
    public function __construct(private readonly ProspectService $prospectService) {}

    public function index(ProspectRelatedListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->prospectService->paginateMessages($uuid, $request->validated());

            return JsonResponser::send(false, 'Messages retrieved.', $this->paginatedPayload($paginator, ProspectMessageResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectMessageController@index');
        }
    }

    /** Always queued; `status` stays "scheduled" until a worker processes it and sets `sent_at`. */
    public function store(ComposeProspectMessageRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $message = $this->prospectService->composeMessage($uuid, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Message sent successfully.', ProspectMessageResource::make($message)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectMessageController@store');
        }
    }

    public function show(string $uuid, string $messageUuid)
    {
        try {
            $message = $this->prospectService->findMessage($uuid, $messageUuid);

            return JsonResponser::send(false, 'Message retrieved.', ProspectMessageResource::make($message)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectMessageController@show');
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
