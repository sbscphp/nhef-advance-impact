<?php

namespace Database\Seeders\Concerns;

use App\Enums\eRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/** Shared so a National Giving Day institution's seeded donation and pledge share the same donor. */
trait SeedsDonors
{
    private const DONOR_PASSWORD = 'password';

    private function donor(string $email, string $firstname, string $lastname, ?string $university = null): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'firstname' => $firstname,
                'lastname' => $lastname,
                'phone_number' => '1908765432',
                'country_code' => '+234',
                'university' => $university,
                'is_active' => true,
                'can_login' => true,
                'password' => Hash::make(self::DONOR_PASSWORD),
            ]
        );

        if ($university !== null && $user->university !== $university) {
            $user->forceFill(['university' => $university])->save();
        }

        if (! $user->hasRole(eRole::CUSTOMER->value)) {
            $user->syncRoles([eRole::CUSTOMER->value]);
        }

        return $user;
    }
}
