<?php

namespace App\Http\Controllers\v1\Admin\Networking;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Networking\AddChannelMembersRequest;
use App\Http\Requests\Admin\Networking\ChannelListRequest;
use App\Http\Requests\Admin\Networking\ChannelMessageListRequest;
use App\Http\Requests\Admin\Networking\CreateChannelRequest;
use App\Http\Requests\Admin\Networking\UpdateChannelRequest;
use App\Http\Resources\Networking\NetworkingChannelMemberResource;
use App\Http\Resources\Networking\NetworkingChannelResource;
use App\Http\Resources\Networking\NetworkingMessageResource;
use App\Models\Admin;
use App\Responser\JsonResponser;
use App\Services\Networking\NetworkingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

/** "View & Manage Community / Forums": admins create/edit channels and manage membership, but never appear as a message sender. */
class ChannelController extends Controller
{
    public function __construct(private readonly NetworkingService $networkingService) {}

    public function index(ChannelListRequest $request)
    {
        try {
            $paginator = $this->networkingService->listChannelsForAdmin($request->validated());

            return JsonResponser::send(false, 'Channels retrieved.', $this->paginatedPayload($paginator, NetworkingChannelResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Networking\ChannelController@index');
        }
    }

    public function show(string $uuid)
    {
        try {
            $channel = $this->networkingService->getChannelForAdmin($uuid);

            return JsonResponser::send(false, 'Channel retrieved.', NetworkingChannelResource::make($channel)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Networking\ChannelController@show');
        }
    }

    public function store(CreateChannelRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $channel = $this->networkingService->createChannelForAdmin(
                $admin,
                $request->validated(),
                $request->validated()['avatar'] ?? null,
                $request,
            );

            return JsonResponser::send(false, 'Channel created.', NetworkingChannelResource::make($channel)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Networking\ChannelController@store');
        }
    }

    public function update(UpdateChannelRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $channel = $this->networkingService->updateChannelForAdmin(
                $admin,
                $uuid,
                $request->validated(),
                $request->validated()['avatar'] ?? null,
                $request,
            );

            return JsonResponser::send(false, 'Channel updated.', NetworkingChannelResource::make($channel)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Networking\ChannelController@update');
        }
    }

    public function destroy(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $this->networkingService->deleteChannelForAdmin($admin, $uuid, $request);

            return JsonResponser::send(false, 'Channel deleted.', null);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Networking\ChannelController@destroy');
        }
    }

    public function lock(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $channel = $this->networkingService->lockChannel($admin, $uuid, $request);

            return JsonResponser::send(false, 'Channel locked.', NetworkingChannelResource::make($channel)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Networking\ChannelController@lock');
        }
    }

    public function unlock(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $channel = $this->networkingService->unlockChannel($admin, $uuid, $request);

            return JsonResponser::send(false, 'Channel unlocked.', NetworkingChannelResource::make($channel)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Networking\ChannelController@unlock');
        }
    }

    public function members(Request $request, string $uuid)
    {
        try {
            $paginator = $this->networkingService->listMembersForAdmin($uuid, $request->query());

            return JsonResponser::send(false, 'Members retrieved.', $this->paginatedPayload($paginator, NetworkingChannelMemberResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Networking\ChannelController@members');
        }
    }

    public function addMembers(AddChannelMembersRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $channel = $this->networkingService->addMembersForAdmin($admin, $uuid, $request->validated()['member_uuids'], $request);

            $addedUuids = $channel->added_member_uuids ?? [];
            $alreadyMemberUuids = $channel->already_member_uuids ?? [];

            $message = match (true) {
                $alreadyMemberUuids === [] => 'Members added.',
                $addedUuids === [] => 'All selected alumni are already members of this channel.',
                default => 'Members added; some were already members of this channel.',
            };

            $payload = NetworkingChannelResource::make($channel)->resolve();
            $payload['added_member_uuids'] = $addedUuids;
            $payload['already_member_uuids'] = $alreadyMemberUuids;

            return JsonResponser::send(false, $message, $payload);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Networking\ChannelController@addMembers');
        }
    }

    public function removeMember(Request $request, string $uuid, string $memberUuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $wasMember = $this->networkingService->removeMemberForAdmin($admin, $uuid, $memberUuid, $request);

            $message = $wasMember ? 'Member removed.' : 'This alumni was not a member of this channel.';

            return JsonResponser::send(false, $message, ['was_member' => $wasMember]);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Networking\ChannelController@removeMember');
        }
    }

    public function messages(ChannelMessageListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->networkingService->listMessagesForAdmin($uuid, $request->validated());

            return JsonResponser::send(false, 'Messages retrieved.', $this->paginatedPayload($paginator, NetworkingMessageResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Networking\ChannelController@messages');
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
