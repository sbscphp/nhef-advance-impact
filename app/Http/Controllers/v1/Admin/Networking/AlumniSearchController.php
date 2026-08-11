<?php

namespace App\Http\Controllers\v1\Admin\Networking;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Networking\AlumniSearchRequest;
use App\Http\Resources\Networking\NetworkingChannelMemberResource;
use App\Responser\JsonResponser;
use App\Services\Networking\NetworkingService;

/** Backs the "Search & Select Alumni" step of Add New Community/Forum. */
class AlumniSearchController extends Controller
{
    public function __construct(private readonly NetworkingService $networkingService) {}

    public function index(AlumniSearchRequest $request)
    {
        try {
            $paginator = $this->networkingService->searchAlumni($request->validated());

            $payload = $paginator->toArray();
            $payload['data'] = NetworkingChannelMemberResource::collection($paginator)->resolve();

            return JsonResponser::send(false, 'Alumni retrieved.', $payload);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Networking\AlumniSearchController@index');
        }
    }
}
