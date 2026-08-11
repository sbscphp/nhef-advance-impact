<?php

namespace Tests\Feature\Networking;

use App\Enums\NetworkingChannelTypeEnum;
use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Models\User;
use App\Services\Networking\NetworkingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminNetworkingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_lock_and_delete_a_community_channel(): void
    {
        $admin = $this->makeAdmin();
        $memberOne = User::factory()->create();
        $memberTwo = User::factory()->create();

        $service = app(NetworkingService::class);
        $request = Request::create('/');

        $channel = $service->createChannelForAdmin(
            $admin,
            [
                'type' => NetworkingChannelTypeEnum::COMMUNITY->value,
                'name' => 'NHEF Test Community',
                'description' => 'A space for testing.',
                'member_uuids' => [$memberOne->uuid],
            ],
            null,
            $request,
        );

        $this->assertSame('NHEF Test Community', $channel->name);
        $this->assertTrue($channel->createdByAdmin->is($admin));
        $this->assertDatabaseHas('networking_channel_members', [
            'channel_id' => $channel->id,
            'user_id' => $memberOne->id,
        ]);

        $channel = $service->addMembersForAdmin($admin, $channel->uuid, [$memberTwo->uuid], $request);
        $this->assertDatabaseHas('networking_channel_members', [
            'channel_id' => $channel->id,
            'user_id' => $memberTwo->id,
        ]);

        $channel = $service->lockChannel($admin, $channel->uuid, $request);
        $this->assertTrue($channel->isLocked());

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('This channel has been locked by an administrator.');

        try {
            $service->sendMessage($memberOne, $channel->uuid, ['body' => 'hello'], null);
        } finally {
            $service->unlockChannel($admin, $channel->uuid, $request);
            $this->assertFalse($service->getChannelForAdmin($channel->uuid)->isLocked());

            // Now that it's unlocked, sending should succeed.
            $message = $service->sendMessage($memberOne, $channel->uuid, ['body' => 'hello again'], null);
            $this->assertSame('hello again', $message->body);

            $service->removeMemberForAdmin($admin, $channel->uuid, $memberTwo->uuid, $request);
            $this->assertDatabaseMissing('networking_channel_members', [
                'channel_id' => $channel->id,
                'user_id' => $memberTwo->id,
                'left_at' => null,
            ]);
        }
    }

    public function test_deleting_a_channel_hard_deletes_its_messages_and_members(): void
    {
        $admin = $this->makeAdmin();
        $member = User::factory()->create();

        $service = app(NetworkingService::class);
        $request = Request::create('/');

        $channel = $service->createChannelForAdmin(
            $admin,
            [
                'type' => NetworkingChannelTypeEnum::FORUM->value,
                'name' => 'NHEF Test Forum',
                'member_uuids' => [$member->uuid],
            ],
            null,
            $request,
        );

        $service->sendMessage($member, $channel->uuid, ['body' => 'a message'], null);

        $service->deleteChannelForAdmin($admin, $channel->uuid, $request);

        $this->assertDatabaseMissing('networking_channels', ['id' => $channel->id]);
        $this->assertDatabaseMissing('networking_channel_members', ['channel_id' => $channel->id]);
        $this->assertDatabaseMissing('networking_messages', ['channel_id' => $channel->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'NETWORKING_CHANNEL_DELETED',
        ]);
    }

    public function test_alumni_search_matches_by_name_email_or_organisation(): void
    {
        User::factory()->create(['firstname' => 'Zara', 'lastname' => 'Okafor', 'organisation_name' => null]);
        User::factory()->create(['firstname' => 'Bola', 'lastname' => 'Adeyemi', 'organisation_name' => 'Zenith Bank']);
        User::factory()->create(['firstname' => 'Chidi', 'lastname' => 'Nwosu', 'organisation_name' => null]);

        $service = app(NetworkingService::class);

        $results = $service->searchAlumni(['search' => 'Zara']);
        $this->assertSame(1, $results->total());

        $results = $service->searchAlumni(['search' => 'Zenith']);
        $this->assertSame(1, $results->total());

        $results = $service->searchAlumni([]);
        $this->assertSame(3, $results->total());
    }

    private function makeAdmin(): Admin
    {
        return Admin::create([
            'name' => 'Super Admin',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_active' => true,
            'can_login' => true,
        ]);
    }
}
