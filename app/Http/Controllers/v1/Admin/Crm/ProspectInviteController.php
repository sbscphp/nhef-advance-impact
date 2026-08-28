<?php

namespace App\Http\Controllers\v1\Admin\Crm;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Crm\ProspectRelatedListRequest;
use App\Http\Requests\Admin\Crm\SendProspectInviteRequest;
use App\Http\Resources\Crm\ProspectInviteResource;
use App\Models\Admin;
use App\Responser\JsonResponser;
use App\Services\Crm\ProspectService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class ProspectInviteController extends Controller
{
    public function __construct(private readonly ProspectService $prospectService) {}

    public function index(ProspectRelatedListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->prospectService->paginateInvites($uuid, $request->validated());

            return JsonResponser::send(false, 'Invites retrieved.', $this->paginatedPayload($paginator, ProspectInviteResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectInviteController@index');
        }
    }

    public function store(SendProspectInviteRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $invite = $this->prospectService->sendInvite($uuid, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Invite sent successfully.', ProspectInviteResource::make($invite)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectInviteController@store');
        }
    }

    public function show(string $uuid, string $inviteUuid)
    {
        try {
            $invite = $this->prospectService->findInvite($uuid, $inviteUuid);

            return JsonResponser::send(false, 'Invite retrieved.', ProspectInviteResource::make($invite)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectInviteController@show');
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
