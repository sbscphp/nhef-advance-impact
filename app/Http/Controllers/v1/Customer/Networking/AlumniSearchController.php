<?php

namespace App\Http\Controllers\v1\Customer\Networking;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Networking\AlumniSearchRequest;
use App\Http\Resources\Networking\NetworkingChannelMemberResource;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Networking\NetworkingService;
use Illuminate\Http\Request;

/** Alumni directory so any alumnus can find and message another directly, without sharing a Community/Forum channel. */
class AlumniSearchController extends Controller
{
    public function __construct(private readonly NetworkingService $networkingService) {}

    public function index(AlumniSearchRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $paginator = $this->networkingService->searchAlumniForCustomer($user, $request->validated());

            $payload = $paginator->toArray();
            $payload['data'] = NetworkingChannelMemberResource::collection($paginator)->resolve();

            return JsonResponser::send(false, 'Alumni retrieved.', $payload);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Networking\AlumniSearchController@index');
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
