<?php

namespace Database\Seeders;

use App\Enums\eRole;
use App\Enums\NetworkingChannelTypeEnum;
use App\Enums\NetworkingReactionEmojiEnum;
use App\Models\Admin;
use App\Models\NetworkingChannel;
use App\Models\NetworkingMessage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A Forum channel, a Community channel, and a direct conversation between two seeded alumni,
 * for local development and Postman testing. Customer users:
 * nhef-networking-1@yopmail.com, nhef-networking-2@yopmail.com (password: "password").
 *
 * php artisan db:seed --class=NetworkingSeeder
 */
class NetworkingSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        $admin = Admin::firstOrCreate(
            ['email' => 'nhef-admin-1@yopmail.com'],
            [
                'name' => 'Super Admin',
                'email_verified_at' => now(),
                'is_active' => true,
                'can_login' => true,
                'password' => Hash::make(self::PASSWORD),
                '2fa' => true,
            ]
        );

        $userOne = $this->createCustomer('nhef-networking-1@yopmail.com', 'Olivia', 'Grant');
        $userTwo = $this->createCustomer('nhef-networking-2@yopmail.com', 'Katherine', 'Moss');

        $forum = NetworkingChannel::firstOrCreate(
            ['type' => NetworkingChannelTypeEnum::FORUM->value, 'name' => 'NHEF Forum'],
            [
                'description' => 'Empowering alumni through tech.',
                'created_by_admin_id' => $admin->id,
            ]
        );

        $community = NetworkingChannel::firstOrCreate(
            ['type' => NetworkingChannelTypeEnum::COMMUNITY->value, 'name' => 'NHEF Community'],
            [
                'description' => 'A space for NHEF alumni to connect.',
                'created_by_admin_id' => $admin->id,
            ]
        );

        foreach ([$forum, $community] as $channel) {
            $channel->members()->syncWithoutDetaching([
                $userOne->id => ['joined_at' => now()->subDays(3)],
                $userTwo->id => ['joined_at' => now()->subDays(3)],
            ]);
        }

        $existingDirect = NetworkingChannel::query()
            ->where('type', NetworkingChannelTypeEnum::DIRECT->value)
            ->whereHas('members', fn ($q) => $q->where('users.id', $userOne->id))
            ->whereHas('members', fn ($q) => $q->where('users.id', $userTwo->id))
            ->first();

        $direct = $existingDirect ?? NetworkingChannel::create(['type' => NetworkingChannelTypeEnum::DIRECT->value]);

        if ($existingDirect === null) {
            $direct->members()->attach([
                $userOne->id => ['joined_at' => now()->subDays(2)],
                $userTwo->id => ['joined_at' => now()->subDays(2)],
            ]);

            $firstMessage = NetworkingMessage::create([
                'channel_id' => $direct->id,
                'sender_id' => $userTwo->id,
                'body' => "Hey Olivia, I've finished with the requirements doc! I made some notes in the gdoc as well for Phoenix to look over.",
                'created_at' => now()->subDay(),
            ]);

            $secondMessage = NetworkingMessage::create([
                'channel_id' => $direct->id,
                'sender_id' => $userOne->id,
                'body' => "Sure thing, I'll have a look today. They're looking great!",
                'created_at' => now()->subMinutes(20),
            ]);

            $secondMessage->reactions()->create([
                'user_id' => $userTwo->id,
                'emoji' => NetworkingReactionEmojiEnum::LOVE->value,
            ]);

            $secondMessage->reactions()->create([
                'user_id' => $userTwo->id,
                'emoji' => NetworkingReactionEmojiEnum::FIRE->value,
            ]);

            unset($firstMessage);
        }
    }

    private function createCustomer(string $email, string $firstname, string $lastname): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'firstname' => $firstname,
                'lastname' => $lastname,
                'phone_number' => '198765432',
                'country_code' => '+234',
                'university' => 'University of Lagos',
                'department' => 'Computer Science',
                'year_of_graduation' => 2021,
                'email_verified_at' => now(),
                '2fa' => false,
                'is_active' => true,
                'can_login' => true,
                'password' => Hash::make(self::PASSWORD),
            ]
        );

        $user->syncRoles([eRole::CUSTOMER->value]);

        return $user;
    }
}
