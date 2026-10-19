<?php

namespace Database\Seeders;

use App\Enums\ConstituentTypeEnum;
use App\Enums\eRole;
use App\Models\User;
use App\Repositories\Contracts\TertiaryInstitution\TertiaryInstitutionRepositoryInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Two customer users: nhef-customer-1|2@yopmail.com (password: "password").
 * Customer 1 has 2FA off; customer 2 has 2FA on.
 *
 * php artisan db:seed --class=CustomerSeeder
 */
class CustomerSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $institutionRepository = app(TertiaryInstitutionRepositoryInterface::class);

        $accounts = [
            [
                'suffix' => '1',
                'firstname' => 'Chidinma',
                'lastname' => 'Okafor',
                '2fa' => false,
                'university' => 'University of Lagos, Lagos',
                'department' => 'Computer Science',
                'year_of_graduation' => 2018,
                'degree_earned' => 'B.Sc',
            ],
            [
                'suffix' => '2',
                'firstname' => 'Tunde',
                'lastname' => 'Bakare',
                '2fa' => true,
                'university' => 'University of Ibadan, Ibadan',
                'department' => 'Economics',
                'year_of_graduation' => 2015,
                'degree_earned' => 'B.Sc',
            ],
        ];

        foreach ($accounts as $row) {
            $email = "nhef-customer-{$row['suffix']}@yopmail.com";
            $tertiaryInstitutionId = $institutionRepository->findOrCreateByName($row['university'])->id;

            $customer = User::firstOrCreate(
                ['email' => $email],
                [
                    'firstname' => $row['firstname'],
                    'lastname' => $row['lastname'],
                    'phone_number' => '198765432',
                    'country_code' => '+234',
                    '2fa' => $row['2fa'],
                    'is_active' => true,
                    'can_login' => true,
                    'password' => Hash::make(self::PASSWORD),
                    'constituent_type' => ConstituentTypeEnum::ALUMNI->value,
                    'tertiary_institution_id' => $tertiaryInstitutionId,
                    'department' => $row['department'],
                    'year_of_graduation' => $row['year_of_graduation'],
                    'degree_earned' => $row['degree_earned'],
                    'employment_status' => 'Employed',
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'onboarded_at' => now(),
                    'last_active_at' => now(),
                ]
            );

            $customer->syncRoles([eRole::CUSTOMER->value]);
        }
    }
}
