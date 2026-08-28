<?php

namespace App\Http\Controllers\v1\Admin\Crm;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Crm\InviteProposalCollaboratorsRequest;
use App\Http\Resources\Crm\ProposalCollaboratorResource;
use App\Models\Admin;
use App\Responser\JsonResponser;
use App\Services\Crm\ProposalService;
use Illuminate\Http\Request;

class ProposalCollaboratorController extends Controller
{
    public function __construct(private readonly ProposalService $proposalService) {}

    public function index(string $uuid, string $proposalUuid)
    {
        try {
            $collaborators = $this->proposalService->listCollaborators($uuid, $proposalUuid);

            return JsonResponser::send(false, 'Collaborators retrieved.', ProposalCollaboratorResource::collection($collaborators)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProposalCollaboratorController@index');
        }
    }

    /** Owner only. Skips the acting admin (already the Owner) and existing Owners. */
    public function store(InviteProposalCollaboratorsRequest $request, string $uuid, string $proposalUuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $collaborators = $this->proposalService->inviteCollaborators($uuid, $proposalUuid, $request->validated('collaborators'), $admin, $request);

            return JsonResponser::send(false, 'Invite(s) sent successfully.', ProposalCollaboratorResource::collection($collaborators)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Crm\ProposalCollaboratorController@store');
        }
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
