<?php

namespace App\Http\Controllers\v1\Admin\Crm;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Crm\CreateProposalRequest;
use App\Http\Requests\Admin\Crm\ProspectRelatedListRequest;
use App\Http\Requests\Admin\Crm\SendProposalToClientRequest;
use App\Http\Requests\Admin\Crm\UpdateProposalRequest;
use App\Http\Resources\Crm\ProposalListResource;
use App\Http\Resources\Crm\ProposalResource;
use App\Models\Admin;
use App\Responser\JsonResponser;
use App\Services\Crm\ProposalService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class ProspectProposalController extends Controller
{
    public function __construct(private readonly ProposalService $proposalService) {}

    public function index(ProspectRelatedListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->proposalService->paginate($uuid, $request->validated());

            return JsonResponser::send(false, 'Proposals retrieved.', $this->paginatedPayload($paginator, ProposalListResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectProposalController@index');
        }
    }

    /** The creating admin is automatically added as the proposal's Owner. */
    public function store(CreateProposalRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $proposal = $this->proposalService->create($uuid, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Proposal created successfully.', ProposalResource::make($proposal)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectProposalController@store');
        }
    }

    public function show(string $uuid, string $proposalUuid)
    {
        try {
            $proposal = $this->proposalService->find($uuid, $proposalUuid);

            return JsonResponser::send(false, 'Proposal retrieved.', ProposalResource::make($proposal)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectProposalController@show');
        }
    }

    /** Owner/editor collaborators only. */
    public function update(UpdateProposalRequest $request, string $uuid, string $proposalUuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $proposal = $this->proposalService->update($uuid, $proposalUuid, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Proposal saved successfully.', ProposalResource::make($proposal)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectProposalController@update');
        }
    }

    /** Owner only. */
    public function destroy(Request $request, string $uuid, string $proposalUuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $this->proposalService->delete($uuid, $proposalUuid, $admin, $request);

            return JsonResponser::send(false, 'Proposal deleted successfully.', []);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectProposalController@destroy');
        }
    }

    /** Owner/editor collaborators only. Duplicate is a new draft owned by the acting admin. */
    public function duplicate(Request $request, string $uuid, string $proposalUuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $proposal = $this->proposalService->duplicate($uuid, $proposalUuid, $admin, $request);

            return JsonResponser::send(false, 'Proposal duplicated successfully.', ProposalResource::make($proposal)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectProposalController@duplicate');
        }
    }

    public function downloadPdf(string $uuid, string $proposalUuid)
    {
        try {
            return $this->proposalService->downloadPdf($uuid, $proposalUuid);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectProposalController@downloadPdf');
        }
    }

    public function downloadWord(string $uuid, string $proposalUuid)
    {
        try {
            return $this->proposalService->downloadWord($uuid, $proposalUuid);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectProposalController@downloadWord');
        }
    }

    /** Owner/editor collaborators only. Always delivered to the prospect's own email too. */
    public function sendToClient(SendProposalToClientRequest $request, string $uuid, string $proposalUuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $proposal = $this->proposalService->sendToClient($uuid, $proposalUuid, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Proposal queued for delivery to the client.', ProposalResource::make($proposal)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectProposalController@sendToClient');
        }
    }

    /** Retries only recipients still owed a delivery; already-sent ones are left untouched. */
    public function resend(Request $request, string $uuid, string $proposalUuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $proposal = $this->proposalService->resend($uuid, $proposalUuid, $admin, $request);

            return JsonResponser::send(false, 'Proposal re-queued for delivery to the client.', ProposalResource::make($proposal)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProspectProposalController@resend');
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
