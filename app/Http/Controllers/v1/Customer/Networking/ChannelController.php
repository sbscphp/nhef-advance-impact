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
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group('Customer Networking / Channels', 'Direct messages plus admin-managed Community and Forum channels. Communities/Forums are created by staff; customers browse, join, and leave them.')]
class ChannelController extends Controller
{
    public function __construct(private readonly NetworkingService $networkingService) {}

    #[Endpoint('My conversations')]
    #[Authenticated]
    #[QueryParam('type', 'string', 'Filter by channel type.', required: false, example: 'direct')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Results per page (max 100).', required: false, example: 15)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Conversations retrieved.',
        'data' => ['current_page' => 1, 'data' => [], 'per_page' => 15, 'total' => 0],
    ], description: 'Channels you are an active member of, most recent activity first.')]
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

    #[Endpoint('Browse Community/Forum channels')]
    #[Authenticated]
    #[QueryParam('type', 'string', 'Filter by community or forum.', required: false, example: 'community')]
    #[QueryParam('search', 'string', 'Filter by channel name.', required: false, example: 'NHEF')]
    #[QueryParam('sort_by', 'string', 'One of: name, created_at, members_count.', required: false, example: 'name')]
    #[QueryParam('sort_direction', 'string', 'asc or desc.', required: false, example: 'asc')]
    #[QueryParam('period', 'string', 'Filters by channel creation date. One of: 1day, 3days, 7days, 14days, 30days, 3months, 6months, 1year, lastyear, custom.', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Start date; required when period=custom.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'End date; required when period=custom.', required: false, example: '2026-08-01')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Results per page (max 100).', required: false, example: 15)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Channels retrieved.',
        'data' => ['current_page' => 1, 'data' => [], 'per_page' => 15, 'total' => 0],
    ], description: 'Discoverable Community/Forum channels, joined or not.')]
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

    #[Endpoint('Channel info')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Channel UUID.', required: true, example: 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Channel retrieved.',
        'data' => ['uuid' => 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6'],
    ], description: 'Channel about info (name, description, member count).')]
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

    #[Endpoint('Start or open a direct conversation')]
    #[Authenticated]
    #[BodyParam('user_uuid', 'string', 'UUID of the user to message.', required: true, example: 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6')]
    #[Response(status: 201, content: [
        'error' => false,
        'message' => 'Conversation ready.',
        'data' => ['uuid' => 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6', 'type' => 'direct'],
    ], description: 'Existing or newly created direct channel with this user.')]
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

    #[Endpoint('Join a Community/Forum channel')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Channel UUID.', required: true, example: 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6')]
    #[Response(status: 200, content: ['error' => false, 'message' => 'Joined channel.', 'data' => ['uuid' => 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6']], description: 'You are now a member.')]
    #[Response(status: 200, content: ['error' => false, 'message' => 'You are already a member of this channel.', 'data' => ['uuid' => 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6']], description: 'No-op: you were already a member.')]
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

    #[Endpoint('Exit a Community/Forum channel')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Channel UUID.', required: true, example: 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6')]
    #[Response(status: 200, content: ['error' => false, 'message' => 'Exited channel.', 'data' => null], description: 'You are no longer a member.')]
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

    #[Endpoint('Channel members')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Channel UUID.', required: true, example: 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Results per page (max 100).', required: false, example: 20)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Members retrieved.',
        'data' => ['current_page' => 1, 'data' => [], 'per_page' => 20, 'total' => 0],
    ], description: 'Members of the channel, for the Members tab.')]
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
