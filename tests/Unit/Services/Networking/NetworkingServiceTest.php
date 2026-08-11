<?php

namespace Tests\Unit\Services\Networking;

use App\Enums\NetworkingChannelTypeEnum;
use App\Exceptions\ApiException;
use App\Models\NetworkingChannel;
use App\Models\NetworkingMessage;
use App\Models\User;
use App\Repositories\Contracts\Networking\NetworkingChannelRepositoryInterface;
use App\Repositories\Contracts\Networking\NetworkingMessageReactionRepositoryInterface;
use App\Repositories\Contracts\Networking\NetworkingMessageRepositoryInterface;
use App\Repositories\Contracts\User\UserRepositoryInterface;
use App\Services\Networking\NetworkingService;
use Mockery;
use Tests\TestCase;

class NetworkingServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_starting_a_direct_conversation_reuses_an_existing_channel(): void
    {
        $viewer = $this->makeUser(1, 'viewer-uuid');
        $otherUser = $this->makeUser(2, 'other-uuid');
        $existingChannel = $this->makeChannel(NetworkingChannelTypeEnum::DIRECT);

        $userRepository = Mockery::mock(UserRepositoryInterface::class);
        $userRepository->shouldReceive('findByUuid')->once()->with('other-uuid')->andReturn($otherUser);

        $channelRepository = Mockery::mock(NetworkingChannelRepositoryInterface::class);
        $channelRepository->shouldReceive('findDirectBetween')->once()->with(1, 2)->andReturn($existingChannel);
        $channelRepository->shouldReceive('otherDirectMember')->once()->andReturn($otherUser);
        $channelRepository->shouldNotReceive('create');

        $service = $this->makeService($channelRepository, userRepository: $userRepository);

        $channel = $service->startDirectConversation($viewer, 'other-uuid');

        $this->assertSame($existingChannel, $channel);
    }

    public function test_starting_a_direct_conversation_creates_a_new_channel_when_none_exists(): void
    {
        $viewer = $this->makeUser(1, 'viewer-uuid');
        $otherUser = $this->makeUser(2, 'other-uuid');
        $newChannel = $this->makeChannel(NetworkingChannelTypeEnum::DIRECT);

        $userRepository = Mockery::mock(UserRepositoryInterface::class);
        $userRepository->shouldReceive('findByUuid')->once()->andReturn($otherUser);

        $channelRepository = Mockery::mock(NetworkingChannelRepositoryInterface::class);
        $channelRepository->shouldReceive('findDirectBetween')->once()->andReturn(null);
        $channelRepository->shouldReceive('create')->once()
            ->with(Mockery::on(fn (array $data): bool => $data['type'] === NetworkingChannelTypeEnum::DIRECT->value
                && str_starts_with($data['description'] ?? '', 'Direct message between')))
            ->andReturn($newChannel);
        $channelRepository->shouldReceive('addMember')->twice()->andReturn(true);

        $service = $this->makeService($channelRepository, userRepository: $userRepository);

        $channel = $service->startDirectConversation($viewer, 'other-uuid');

        $this->assertSame($newChannel, $channel);
    }

    public function test_a_non_member_cannot_send_a_message(): void
    {
        $viewer = $this->makeUser(1, 'viewer-uuid');
        $channel = $this->makeChannel(NetworkingChannelTypeEnum::COMMUNITY);

        $channelRepository = Mockery::mock(NetworkingChannelRepositoryInterface::class);
        $channelRepository->shouldReceive('findByUuid')->once()->andReturn($channel);
        $channelRepository->shouldReceive('isMember')->once()->andReturn(false);

        $messageRepository = Mockery::mock(NetworkingMessageRepositoryInterface::class);
        $messageRepository->shouldNotReceive('create');

        $service = $this->makeService($channelRepository, $messageRepository);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('You must join this channel to do that.');

        $service->sendMessage($viewer, $channel->uuid, ['body' => 'hello'], null);
    }

    public function test_reacting_with_an_emoji_outside_the_closed_set_is_rejected(): void
    {
        $viewer = $this->makeUser(1, 'viewer-uuid');
        $message = $this->makeMessage();

        $messageRepository = Mockery::mock(NetworkingMessageRepositoryInterface::class);
        $messageRepository->shouldNotReceive('findByUuid');

        $reactionRepository = Mockery::mock(NetworkingMessageReactionRepositoryInterface::class);
        $reactionRepository->shouldNotReceive('add');

        $service = $this->makeService(reactionRepository: $reactionRepository, messageRepository: $messageRepository);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid reaction.');

        $service->react($viewer, $message->uuid, 'not-a-real-emoji');
    }

    private function makeService(
        ?NetworkingChannelRepositoryInterface $channelRepository = null,
        ?NetworkingMessageRepositoryInterface $messageRepository = null,
        ?NetworkingMessageReactionRepositoryInterface $reactionRepository = null,
        ?UserRepositoryInterface $userRepository = null,
    ): NetworkingService {
        return new NetworkingService(
            $channelRepository ?? Mockery::mock(NetworkingChannelRepositoryInterface::class),
            $messageRepository ?? Mockery::mock(NetworkingMessageRepositoryInterface::class),
            $reactionRepository ?? Mockery::mock(NetworkingMessageReactionRepositoryInterface::class),
            $userRepository ?? Mockery::mock(UserRepositoryInterface::class),
        );
    }

    private function makeUser(int $id, string $uuid): User
    {
        $user = new User;
        $user->id = $id;
        $user->uuid = $uuid;
        $user->firstname = 'Test';
        $user->lastname = 'User';

        return $user;
    }

    private function makeChannel(NetworkingChannelTypeEnum $type): NetworkingChannel
    {
        $channel = new NetworkingChannel;
        $channel->id = 10;
        $channel->uuid = 'channel-uuid';
        $channel->type = $type->value;

        return $channel;
    }

    private function makeMessage(): NetworkingMessage
    {
        $message = new NetworkingMessage;
        $message->id = 100;
        $message->uuid = 'message-uuid';

        return $message;
    }
}
