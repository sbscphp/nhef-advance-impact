<?php

namespace Database\Seeders\Concerns;

use App\Enums\eRole;
use App\Models\User;
use App\Repositories\Contracts\TertiaryInstitution\TertiaryInstitutionRepositoryInterface;
use Illuminate\Support\Facades\Hash;

/** Shared so a National Giving Day institution's seeded donation and pledge share the same donor. */
trait SeedsDonors
{
    private const DONOR_PASSWORD = 'password';

    private function donor(string $email, string $firstname, string $lastname, ?string $university = null): User
    {
        $tertiaryInstitutionId = $university !== null
            ? app(TertiaryInstitutionRepositoryInterface::class)->findOrCreateByName($university)->id
            : null;

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'firstname' => $firstname,
                'lastname' => $lastname,
                'phone_number' => '1908765432',
                'country_code' => '+234',
                'tertiary_institution_id' => $tertiaryInstitutionId,
                'is_active' => true,
                'can_login' => true,
                'password' => Hash::make(self::DONOR_PASSWORD),
            ]
        );

        if ($tertiaryInstitutionId !== null && $user->tertiary_institution_id !== $tertiaryInstitutionId) {
            $user->forceFill(['tertiary_institution_id' => $tertiaryInstitutionId])->save();
        }

        if (! $user->hasRole(eRole::CUSTOMER->value)) {
            $user->syncRoles([eRole::CUSTOMER->value]);
        }

        return $user;
    }
}
