<?php

namespace App\Http\Controllers\v1\Customer\Networking;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Networking\ChannelListRequest;
use App\Http\Requests\Customer\Networking\StartDirectConversationRequest;
use App\Http\Resources\Networking\NetworkingChannelMemberResource;
use App\Http\Resources\Networking\NetworkingChannelResource;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Networking\NetworkingService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ChannelController extends Controller
{
    public function __construct(private readonly NetworkingService $networkingService) {}

    public function index(ChannelListRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $paginator = $this->networkingService->listMyConversations($user, $request->validated());

            return JsonResponser::send(false, 'Conversations retrieved.', $this->paginatedPayload($paginator), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Networking\ChannelController@index');
        }
    }

    public function browse(ChannelListRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $paginator = $this->networkingService->listBrowsableChannels($user, $request->validated());

            return JsonResponser::send(false, 'Channels retrieved.', $this->paginatedPayload($paginator), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Networking\ChannelController@browse');
        }
    }

    public function show(Request $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $channel = $this->networkingService->getChannel($user, $uuid);

            return JsonResponser::send(false, 'Channel retrieved.', NetworkingChannelResource::make($channel), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Networking\ChannelController@show');
        }
    }

    public function startDirect(StartDirectConversationRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $channel = $this->networkingService->startDirectConversation($user, $request->validated()['user_uuid']);

            return JsonResponser::send(false, 'Conversation ready.', NetworkingChannelResource::make($channel), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Networking\ChannelController@startDirect');
        }
    }

    public function join(Request $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $channel = $this->networkingService->joinChannel($user, $uuid);

            $message = $channel->was_already_member ? 'You are already a member of this channel.' : 'Joined channel.';

            return JsonResponser::send(false, $message, NetworkingChannelResource::make($channel), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Networking\ChannelController@join');
        }
    }

    public function leave(Request $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $this->networkingService->leaveChannel($user, $uuid);

            return JsonResponser::send(false, 'Exited channel.', null, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Networking\ChannelController@leave');
        }
    }

    public function members(Request $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $paginator = $this->networkingService->listMembers($user, $uuid, $request->query());

            $payload = $paginator->toArray();
            $payload['data'] = NetworkingChannelMemberResource::collection($paginator)->resolve();

            return JsonResponser::send(false, 'Members retrieved.', $payload, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Networking\ChannelController@members');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator): array
    {
        $payload = $paginator->toArray();
        $payload['data'] = NetworkingChannelResource::collection($paginator)->resolve();

        return $payload;
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
