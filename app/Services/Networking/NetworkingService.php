<?php

namespace App\Services\Networking;

use App\Enums\AuditActionEnum;
use App\Enums\ModuleEnums;
use App\Enums\NetworkingChannelTypeEnum;
use App\Enums\NetworkingReactionEmojiEnum;
use App\Enums\UserTypeEnum;
use App\Events\Networking\NetworkingMessageReactionChanged;
use App\Events\Networking\NetworkingMessageSent;
use App\Events\Networking\NetworkingTypingSignal;
use App\Exceptions\ApiException;
use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Models\Admin;
use App\Models\NetworkingChannel;
use App\Models\NetworkingMessage;
use App\Models\User;
use App\Repositories\Contracts\Networking\NetworkingChannelRepositoryInterface;
use App\Repositories\Contracts\Networking\NetworkingMessageReactionRepositoryInterface;
use App\Repositories\Contracts\Networking\NetworkingMessageRepositoryInterface;
use App\Repositories\Contracts\User\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;

class NetworkingService
{
    public function __construct(
        private readonly NetworkingChannelRepositoryInterface $channelRepository,
        private readonly NetworkingMessageRepositoryInterface $messageRepository,
        private readonly NetworkingMessageReactionRepositoryInterface $reactionRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listMyConversations(User $user, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
        $paginator = $this->channelRepository->paginateConversationsForUser($user->id, $filters, $perPage);

        foreach ($paginator->items() as $channel) {
            $this->attachDirectChannelDisplayData($channel, $user->id);
        }

        return $paginator;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listBrowsableChannels(User $user, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
        $paginator = $this->channelRepository->paginateBrowsable($filters, $perPage);
        $memberChannelIds = $this->channelRepository->memberChannelIdsForUser($user->id);

        foreach ($paginator->items() as $channel) {
            $channel->setAttribute('viewer_is_member', in_array($channel->id, $memberChannelIds, true));
        }

        return $paginator;
    }

    public function getChannel(User $user, string $uuid): NetworkingChannel
    {
        $channel = $this->findChannelOrFail($uuid);
        $channel->setAttribute('viewer_is_member', $this->channelRepository->isMember($channel->id, $user->id));

        if ($channel->isDirect()) {
            $this->attachDirectChannelDisplayData($channel, $user->id);
        }

        return $channel;
    }

    public function startDirectConversation(User $user, string $otherUserUuid): NetworkingChannel
    {
        $otherUser = $this->userRepository->findByUuid($otherUserUuid);

        if (! $otherUser instanceof User) {
            throw new ApiException('User not found.', 404);
        }

        if ($otherUser->id === $user->id) {
            throw new ApiException('You cannot start a conversation with yourself.', 422);
        }

        $existing = $this->channelRepository->findDirectBetween($user->id, $otherUser->id);

        if ($existing instanceof NetworkingChannel) {
            $this->attachDirectChannelDisplayData($existing, $user->id);

            return $existing;
        }

        // mb_substr below: MySQL strict mode throws on overlong values for this VARCHAR(255)
        // column, and unusually long names/emails could otherwise break DM creation entirely.
        $description = "Direct message between {$user->displayName()} ({$user->email}) and {$otherUser->displayName()} ({$otherUser->email})";

        $channel = $this->channelRepository->create([
            'type' => NetworkingChannelTypeEnum::DIRECT->value,
            'description' => mb_substr($description, 0, 255),
        ]);

        $this->channelRepository->addMember($channel, $user->id);
        $this->channelRepository->addMember($channel, $otherUser->id);

        $channel->setAttribute('other_member', $otherUser);

        return $channel;
    }

    public function joinChannel(User $user, string $uuid): NetworkingChannel
    {
        $channel = $this->findChannelOrFail($uuid);

        if ($channel->isDirect()) {
            throw new ApiException('Direct conversations cannot be joined.', 422);
        }

        if ($channel->is_archived) {
            throw new ApiException('This channel has been archived.', 422);
        }

        $wasAdded = $this->channelRepository->addMember($channel, $user->id);

        // Re-fetch: $channel's members_count was loaded before addMember() ran, so it would
        // otherwise report the pre-join count.
        $refreshed = $this->findChannelOrFail($uuid);
        $refreshed->setAttribute('viewer_is_member', true);
        $refreshed->setAttribute('was_already_member', ! $wasAdded);

        return $refreshed;
    }

    public function leaveChannel(User $user, string $uuid): void
    {
        $channel = $this->findChannelOrFail($uuid);

        if ($channel->isDirect()) {
            throw new ApiException('Direct conversations cannot be exited.', 422);
        }

        if (! $this->channelRepository->isMember($channel->id, $user->id)) {
            throw new ApiException('You are not a member of this channel.', 422);
        }

        $this->channelRepository->removeMember($channel, $user->id);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listMembers(User $user, string $channelUuid, array $filters): LengthAwarePaginator
    {
        $channel = $this->findChannelOrFail($channelUuid);
        $this->requireMembership($channel, $user);

        $perPage = max(1, min((int) ($filters['per_page'] ?? 20), 100));

        return $channel->members()->with('tertiaryInstitution')->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function sendMessage(User $user, string $channelUuid, array $validated, ?UploadedFile $attachment): NetworkingMessage
    {
        $channel = $this->findChannelOrFail($channelUuid);
        $this->requireMembership($channel, $user);

        if ($channel->isLocked()) {
            throw new ApiException('This channel has been locked by an administrator.', 422);
        }

        $attachmentUrl = $attachment !== null
            ? FileUploadHelper::smartSingleFileUpload($attachment, 'networking/attachments')
            : null;

        $message = $this->messageRepository->create([
            'channel_id' => $channel->id,
            'sender_id' => $user->id,
            'body' => $validated['body'] ?? null,
            'attachment_url' => $attachmentUrl,
            'attachment_name' => $attachment?->getClientOriginalName(),
            'attachment_mime' => $attachment?->getMimeType(),
            'attachment_size' => $attachment?->getSize(),
        ]);

        $message->setRelation('channel', $channel);
        $message->setRelation('sender', $user);

        event(new NetworkingMessageSent($message));

        return $message;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listMessages(User $user, string $channelUuid, array $filters): LengthAwarePaginator
    {
        $channel = $this->findChannelOrFail($channelUuid);
        $this->requireMembership($channel, $user);

        $perPage = max(1, min((int) ($filters['per_page'] ?? 20), 100));

        return $this->messageRepository->paginateForChannel($channel->id, $filters, $perPage);
    }

    public function markRead(User $user, string $channelUuid): void
    {
        $channel = $this->findChannelOrFail($channelUuid);
        $this->requireMembership($channel, $user);

        $this->channelRepository->touchLastRead($channel, $user->id);
    }

    public function sendTypingSignal(User $user, string $channelUuid): void
    {
        $channel = $this->findChannelOrFail($channelUuid);
        $this->requireMembership($channel, $user);

        event(new NetworkingTypingSignal($channel->uuid, $user));
    }

    public function react(User $user, string $messageUuid, string $emoji): void
    {
        if (! in_array($emoji, NetworkingReactionEmojiEnum::values(), true)) {
            throw new ApiException('Invalid reaction.', 422);
        }

        $message = $this->findMessageOrFail($messageUuid);
        $this->requireMembership($message->channel, $user);

        if ($this->reactionRepository->findForUser($message->id, $user->id, $emoji) !== null) {
            throw new ApiException('You have already reacted with this emoji.', 422);
        }

        $this->reactionRepository->add($message, $user->id, $emoji);

        event(new NetworkingMessageReactionChanged($message, $message->channel->uuid));
    }

    public function unreact(User $user, string $messageUuid, string $emoji): void
    {
        $message = $this->findMessageOrFail($messageUuid);
        $this->requireMembership($message->channel, $user);

        $this->reactionRepository->remove($message, $user->id, $emoji);

        event(new NetworkingMessageReactionChanged($message, $message->channel->uuid));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listChannelsForAdmin(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->channelRepository->paginateForAdmin($filters, $perPage);
    }

    public function getChannelForAdmin(string $uuid): NetworkingChannel
    {
        return $this->findChannelOrFail($uuid);
    }

    /**
     * Backs "Add New Community" / "Add New Forum": creates the channel and, if the caller
     * carried members over from the "Search & Select Alumni" step, adds them in the same call.
     *
     * @param  array<string, mixed>  $validated
     */
    public function createChannelForAdmin(Admin $actor, array $validated, UploadedFile|string|null $avatar, Request $request): NetworkingChannel
    {
        $avatarUrl = FileUploadHelper::smartSingleFileUpload($avatar, 'networking/avatars');

        $channel = $this->channelRepository->create([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'avatar_url' => $avatarUrl,
            'created_by_admin_id' => $actor->id,
        ]);

        $addedUuids = [];
        foreach ($validated['member_uuids'] ?? [] as $memberUuid) {
            $member = $this->userRepository->findByUuid($memberUuid);
            if ($member instanceof User) {
                $this->channelRepository->addMember($channel, $member->id);
                $addedUuids[] = $member->uuid;
            }
        }

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::NETWORKING_CHANNEL_CREATED,
            $request,
            $actor->uuid,
            ['channel_uuid' => $channel->uuid, 'type' => $channel->type, 'name' => $channel->name, 'member_uuids' => $addedUuids],
            $actor->displayName().' created a '.$channel->type.' channel: '.$channel->name.'.',
            NetworkingChannel::class,
            $channel->uuid,
            ModuleEnums::networking,
            201,
        );

        return $this->findChannelOrFail($channel->uuid);
    }

    /**
     * Backs the "About" tab of Community/Forum Details: name, description, avatar only.
     * Membership is managed separately via {@see addMembersForAdmin()}/{@see removeMemberForAdmin()}.
     *
     * @param  array<string, mixed>  $validated
     */
    public function updateChannelForAdmin(Admin $actor, string $uuid, array $validated, UploadedFile|string|null $avatar, Request $request): NetworkingChannel
    {
        $channel = $this->findChannelOrFail($uuid);

        if ($channel->isDirect()) {
            throw new ApiException('Direct conversations cannot be edited.', 422);
        }

        $updates = [];
        foreach (['name', 'description'] as $field) {
            if (array_key_exists($field, $validated)) {
                $updates[$field] = $validated[$field];
            }
        }

        if ($avatar !== null) {
            $updates['avatar_url'] = FileUploadHelper::smartSingleFileUpload($avatar, 'networking/avatars');
        }

        if ($updates === []) {
            return $channel;
        }

        $channel = $this->channelRepository->update($channel, $updates);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::NETWORKING_CHANNEL_UPDATED,
            $request,
            $actor->uuid,
            ['channel_uuid' => $channel->uuid, 'fields' => array_keys($updates)],
            $actor->displayName().' updated a '.$channel->type.' channel: '.$channel->name.'.',
            NetworkingChannel::class,
            $channel->uuid,
            ModuleEnums::networking,
            200,
        );

        return $channel;
    }

    /**
     * Sets `already_member_uuids` and `added_member_uuids` on the returned channel so callers can
     * tell apart alumni who were newly added from ones who were already in the channel.
     *
     * @param  list<string>  $memberUuids
     */
    public function addMembersForAdmin(Admin $actor, string $uuid, array $memberUuids, Request $request): NetworkingChannel
    {
        $channel = $this->findChannelOrFail($uuid);

        if ($channel->isDirect()) {
            throw new ApiException('Direct conversations cannot have members added.', 422);
        }

        $addedUuids = [];
        $alreadyMemberUuids = [];
        foreach ($memberUuids as $memberUuid) {
            $member = $this->userRepository->findByUuid($memberUuid);
            if (! $member instanceof User) {
                throw new ApiException("Alumni {$memberUuid} not found.", 404);
            }

            if ($this->channelRepository->addMember($channel, $member->id)) {
                $addedUuids[] = $member->uuid;
            } else {
                $alreadyMemberUuids[] = $member->uuid;
            }
        }

        if ($addedUuids !== []) {
            GeneralHelper::storeAuditLog(
                UserTypeEnum::ADMIN,
                AuditActionEnum::NETWORKING_CHANNEL_MEMBER_ADDED,
                $request,
                $actor->uuid,
                ['channel_uuid' => $channel->uuid, 'member_uuids' => $addedUuids, 'already_member_uuids' => $alreadyMemberUuids],
                $actor->displayName().' added '.count($addedUuids).' member(s) to '.$channel->name.'.',
                NetworkingChannel::class,
                $channel->uuid,
                ModuleEnums::networking,
                200,
            );
        }

        $refreshed = $this->findChannelOrFail($channel->uuid);
        $refreshed->setAttribute('added_member_uuids', $addedUuids);
        $refreshed->setAttribute('already_member_uuids', $alreadyMemberUuids);

        return $refreshed;
    }

    /**
     * @return bool true if the alumni was an active member and got removed, false if they were
     *              already not a member of this channel.
     */
    public function removeMemberForAdmin(Admin $actor, string $uuid, string $memberUuid, Request $request): bool
    {
        $channel = $this->findChannelOrFail($uuid);

        if ($channel->isDirect()) {
            throw new ApiException('Direct conversations cannot have members removed.', 422);
        }

        $member = $this->userRepository->findByUuid($memberUuid);
        if (! $member instanceof User) {
            throw new ApiException('Alumni not found.', 404);
        }

        $wasRemoved = $this->channelRepository->removeMember($channel, $member->id);

        if ($wasRemoved) {
            GeneralHelper::storeAuditLog(
                UserTypeEnum::ADMIN,
                AuditActionEnum::NETWORKING_CHANNEL_MEMBER_REMOVED,
                $request,
                $actor->uuid,
                ['channel_uuid' => $channel->uuid, 'member_uuid' => $member->uuid],
                $actor->displayName().' removed '.$member->displayName().' from '.$channel->name.'.',
                NetworkingChannel::class,
                $channel->uuid,
                ModuleEnums::networking,
                200,
            );
        }

        return $wasRemoved;
    }

    /** Freezes messaging: members keep read access and can still be added/removed, but {@see sendMessage()} rejects new messages. */
    public function lockChannel(Admin $actor, string $uuid, Request $request): NetworkingChannel
    {
        $channel = $this->findChannelOrFail($uuid);

        if ($channel->isDirect()) {
            throw new ApiException('Direct conversations cannot be locked.', 422);
        }

        if ($channel->isLocked()) {
            throw new ApiException('This channel is already locked.', 422);
        }

        $channel = $this->channelRepository->update($channel, ['is_locked' => true, 'locked_at' => now()]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::NETWORKING_CHANNEL_LOCKED,
            $request,
            $actor->uuid,
            ['channel_uuid' => $channel->uuid],
            $actor->displayName().' locked the channel: '.$channel->name.'.',
            NetworkingChannel::class,
            $channel->uuid,
            ModuleEnums::networking,
            200,
        );

        return $channel;
    }

    public function unlockChannel(Admin $actor, string $uuid, Request $request): NetworkingChannel
    {
        $channel = $this->findChannelOrFail($uuid);

        if (! $channel->isLocked()) {
            throw new ApiException('This channel is not locked.', 422);
        }

        $channel = $this->channelRepository->update($channel, ['is_locked' => false, 'locked_at' => null]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::NETWORKING_CHANNEL_UNLOCKED,
            $request,
            $actor->uuid,
            ['channel_uuid' => $channel->uuid],
            $actor->displayName().' unlocked the channel: '.$channel->name.'.',
            NetworkingChannel::class,
            $channel->uuid,
            ModuleEnums::networking,
            200,
        );

        return $channel;
    }

    /** Hard delete, matching the "all records will be lost" warning in the Delete Channel confirmation. */
    public function deleteChannelForAdmin(Admin $actor, string $uuid, Request $request): void
    {
        $channel = $this->findChannelOrFail($uuid);

        if ($channel->isDirect()) {
            throw new ApiException('Direct conversations cannot be deleted.', 422);
        }

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::NETWORKING_CHANNEL_DELETED,
            $request,
            $actor->uuid,
            [
                'channel_uuid' => $channel->uuid,
                'type' => $channel->type,
                'name' => $channel->name,
                'member_count' => $channel->members_count,
            ],
            $actor->displayName().' deleted the '.$channel->type.' channel: '.$channel->name.'.',
            NetworkingChannel::class,
            $channel->uuid,
            ModuleEnums::networking,
            200,
        );

        $this->channelRepository->delete($channel);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listMembersForAdmin(string $uuid, array $filters): LengthAwarePaginator
    {
        $channel = $this->findChannelOrFail($uuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 20), 100));

        return $channel->members()->with('tertiaryInstitution')->paginate($perPage);
    }

    /**
     * Read-only: the admin Networking screens let staff review a channel's messages for
     * oversight, but admins never appear as a message sender.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listMessagesForAdmin(string $uuid, array $filters): LengthAwarePaginator
    {
        $channel = $this->findChannelOrFail($uuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 20), 100));

        return $this->messageRepository->paginateForChannel($channel->id, $filters, $perPage);
    }

    /**
     * Backs the "Search & Select Alumni" step of Add New Community/Forum.
     *
     * @param  array<string, mixed>  $filters
     */
    public function searchAlumni(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 20), 100));

        return $this->userRepository->paginateAlumniSearch($filters, $perPage);
    }

    /**
     * Alumni directory for customers, so anyone can find and start a direct message with any
     * other alumni without first sharing a Community/Forum channel. Excludes the viewer from
     * their own results.
     *
     * @param  array<string, mixed>  $filters
     */
    public function searchAlumniForCustomer(User $viewer, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 20), 100));

        return $this->userRepository->paginateAlumniSearch([...$filters, 'exclude_user_id' => $viewer->id], $perPage);
    }

    private function findChannelOrFail(string $uuid): NetworkingChannel
    {
        $channel = $this->channelRepository->findByUuid($uuid);

        if (! $channel instanceof NetworkingChannel) {
            throw new ApiException('Channel not found.', 404);
        }

        return $channel;
    }

    private function findMessageOrFail(string $uuid): NetworkingMessage
    {
        $message = $this->messageRepository->findByUuid($uuid);

        if (! $message instanceof NetworkingMessage) {
            throw new ApiException('Message not found.', 404);
        }

        return $message;
    }

    private function requireMembership(NetworkingChannel $channel, User $user): void
    {
        if (! $this->channelRepository->isMember($channel->id, $user->id)) {
            throw new ApiException('You must join this channel to do that.', 403);
        }
    }

    private function attachDirectChannelDisplayData(NetworkingChannel $channel, int $viewerId): void
    {
        if (! $channel->isDirect()) {
            return;
        }

        $channel->setAttribute('other_member', $this->channelRepository->otherDirectMember($channel->id, $viewerId));
    }
}
