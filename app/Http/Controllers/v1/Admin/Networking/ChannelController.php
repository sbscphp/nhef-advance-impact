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
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;

/** "View & Manage Community / Forums": admins create/edit channels and manage membership, but never appear as a message sender. */
#[Group('Admin / Networking / Channels', 'Create and manage Community/Forum channels and their membership. Requires the relevant `networking.*` permission per action.')]
class ChannelController extends Controller
{
    public function __construct(private readonly NetworkingService $networkingService) {}

    #[Endpoint('List channels')]
    #[Authenticated]
    #[QueryParam('type', 'string', 'Filter by community or forum.', required: false, example: 'community')]
    #[QueryParam('search', 'string', 'Filter by channel name.', required: false, example: 'NHEF')]
    #[QueryParam('sort_by', 'string', 'One of: name, created_at, members_count.', required: false, example: 'members_count')]
    #[QueryParam('sort_direction', 'string', 'asc or desc.', required: false, example: 'desc')]
    #[QueryParam('period', 'string', 'Filters by channel creation date. One of: 1day, 3days, 7days, 14days, 30days, 3months, 6months, 1year, lastyear, custom.', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Start date; required when period=custom.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'End date; required when period=custom.', required: false, example: '2026-08-01')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Results per page (max 100).', required: false, example: 15)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Channels retrieved.',
        'data' => ['current_page' => 1, 'data' => [], 'per_page' => 15, 'total' => 0],
    ], description: 'Every Community/Forum channel, for the admin "Community/Forums" screen.')]
    public function index(ChannelListRequest $request)
    {
        try {
            $paginator = $this->networkingService->listChannelsForAdmin($request->validated());

            return JsonResponser::send(false, 'Channels retrieved.', $this->paginatedPayload($paginator, NetworkingChannelResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Networking\ChannelController@index');
        }
    }

    #[Endpoint('View a channel')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Channel UUID.', required: true, example: 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Channel retrieved.',
        'data' => ['uuid' => 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6'],
    ], description: 'Full channel detail.')]
    public function show(string $uuid)
    {
        try {
            $channel = $this->networkingService->getChannelForAdmin($uuid);

            return JsonResponser::send(false, 'Channel retrieved.', NetworkingChannelResource::make($channel)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Networking\ChannelController@show');
        }
    }

    #[Endpoint('Create a channel', 'Backs both "Add New Community" and "Add New Forum"; the two flows differ only by `type`.')]
    #[Authenticated]
    #[BodyParam('type', 'string', 'community or forum.', required: true, example: 'community')]
    #[BodyParam('name', 'string', 'Channel name.', required: true, example: 'Lagos Alumni Network')]
    #[BodyParam('description', 'string', 'Channel description (max 50 chars).', required: false, example: 'Connect with fellow Lagos-based alumni.')]
    #[BodyParam('avatar', 'file', 'Channel avatar (JPG, PNG, GIF, or WEBP; max 5MB).', required: false)]
    #[BodyParam('member_uuids', 'string[]', 'UUIDs of members to add on creation.', required: false, example: ['a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6'])]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Channel created.',
        'data' => ['uuid' => 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6', 'type' => 'community', 'name' => 'Lagos Alumni Network'],
    ], description: 'Channel created.')]
    public function store(CreateChannelRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $channel = $this->networkingService->createChannelForAdmin(
                $admin,
                $request->validated(),
                $request->file('avatar'),
                $request,
            );

            return JsonResponser::send(false, 'Channel created.', NetworkingChannelResource::make($channel)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Networking\ChannelController@store');
        }
    }

    #[Endpoint('Edit a channel', 'Backs the "About" tab of Community/Forum Details: name, description, avatar only.')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Channel UUID.', required: true, example: 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6')]
    #[BodyParam('name', 'string', 'Channel name.', required: false, example: 'Lagos Alumni Network')]
    #[BodyParam('description', 'string', 'Channel description (max 50 chars).', required: false, example: 'Connect with fellow Lagos-based alumni.')]
    #[BodyParam('avatar', 'file', 'Channel avatar (JPG, PNG, GIF, or WEBP; max 5MB).', required: false)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Channel updated.',
        'data' => ['uuid' => 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6', 'name' => 'Lagos Alumni Network'],
    ], description: 'Channel updated.')]
    public function update(UpdateChannelRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $channel = $this->networkingService->updateChannelForAdmin(
                $admin,
                $uuid,
                $request->validated(),
                $request->file('avatar'),
                $request,
            );

            return JsonResponser::send(false, 'Channel updated.', NetworkingChannelResource::make($channel)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Networking\ChannelController@update');
        }
    }

    #[Endpoint('Delete a channel')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Channel UUID.', required: true, example: 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6')]
    #[Response(status: 200, content: ['error' => false, 'message' => 'Channel deleted.', 'data' => null], description: 'Channel and its messages removed.')]
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

    #[Endpoint('Lock a channel', 'Prevents non-admin members from posting new messages.')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Channel UUID.', required: true, example: 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Channel locked.',
        'data' => ['uuid' => 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6', 'is_locked' => true],
    ], description: 'Channel is now locked.')]
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

    #[Endpoint('Unlock a channel')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Channel UUID.', required: true, example: 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Channel unlocked.',
        'data' => ['uuid' => 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6', 'is_locked' => false],
    ], description: 'Channel is now unlocked.')]
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
            $paginator = $this->networkingService->listMembersForAdmin($uuid, $request->query());

            return JsonResponser::send(false, 'Members retrieved.', $this->paginatedPayload($paginator, NetworkingChannelMemberResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Networking\ChannelController@members');
        }
    }

    #[Endpoint('Add members', 'Backs "+ Add People" on the Members tab. Alumni already in the channel are skipped and reported back in `already_member_uuids` rather than failing the whole request.')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Channel UUID.', required: true, example: 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6')]
    #[BodyParam('member_uuids', 'string[]', 'UUIDs of the alumni to add.', required: true, example: ['a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6'])]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Members added.',
        'data' => ['uuid' => 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6', 'added_member_uuids' => ['a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6'], 'already_member_uuids' => []],
    ], description: 'Updated channel, plus which UUIDs were newly added vs already members.')]
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

    #[Endpoint('Remove a member')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Channel UUID.', required: true, example: 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6')]
    #[UrlParam('memberUuid', 'string', "The member's UUID.", required: true, example: 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7')]
    #[Response(status: 200, content: ['error' => false, 'message' => 'Member removed.', 'data' => ['was_member' => true]], description: 'Member removed from the channel.')]
    #[Response(status: 200, content: ['error' => false, 'message' => 'This alumni was not a member of this channel.', 'data' => ['was_member' => false]], description: 'No-op: the alumni was not an active member.')]
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

    #[Endpoint('Channel messages', 'Read-only view of a channel\'s message history for moderation; admins cannot send messages here.')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Channel UUID.', required: true, example: 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6')]
    #[QueryParam('search', 'string', 'Filter by message text.', required: false, example: 'requirements')]
    #[QueryParam('sort_direction', 'string', 'asc (oldest first) or desc (newest first, default).', required: false, example: 'asc')]
    #[QueryParam('period', 'string', 'Filters by message sent date. One of: 1day, 3days, 7days, 14days, 30days, 3months, 6months, 1year, lastyear, custom.', required: false, example: '7days')]
    #[QueryParam('start_date', 'string', 'Start date; required when period=custom.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'End date; required when period=custom.', required: false, example: '2026-08-01')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Results per page (max 100).', required: false, example: 20)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Messages retrieved.',
        'data' => ['current_page' => 1, 'data' => [], 'per_page' => 20, 'total' => 0],
    ], description: 'Most recent messages first.')]
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
