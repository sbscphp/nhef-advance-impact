<?php

namespace Database\Seeders;

use App\Enums\ConstituentTypeEnum;
use App\Enums\DegreeEnum;
use App\Enums\eRole;
use App\Enums\EmploymentStatusEnum;
use App\Models\Country;
use App\Models\User;
use App\Repositories\Contracts\TertiaryInstitution\TertiaryInstitutionRepositoryInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Fixed-credential demo accounts for the two "Sign Up as" flavours CustomerSeeder doesn't cover:
 * nhef-non-alumni@yopmail.com (non_alumni) and nhef-organization@yopmail.com (organization),
 * same password ("password") and CUSTOMER role as CustomerSeeder's two alumni accounts. Named by
 * type rather than number so which login is which doesn't need to be memorized.
 *
 * php artisan db:seed --class=ConstituentTypeDemoSeeder
 */
class ConstituentTypeDemoSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $institutionRepository = app(TertiaryInstitutionRepositoryInterface::class);
        $nigeria = Country::query()->where('name', 'Nigeria')->first();

        $this->seedNonAlumni($institutionRepository, $nigeria);
        $this->seedOrganization($institutionRepository, $nigeria);
    }

    private function seedNonAlumni(TertiaryInstitutionRepositoryInterface $institutionRepository, ?Country $nigeria): void
    {
        $tertiaryInstitutionId = $institutionRepository->findOrCreateByName('University of Lagos, Lagos')->id;

        $user = User::firstOrCreate(
            ['email' => 'nhef-non-alumni@yopmail.com'],
            [
                'firstname' => 'Ngozi',
                'lastname' => 'Eze',
                'phone_number' => '198765433',
                'country_code' => '+234',
                '2fa' => false,
                'is_active' => true,
                'can_login' => true,
                'password' => Hash::make(self::PASSWORD),
                'constituent_type' => ConstituentTypeEnum::NON_ALUMNI->value,
                'tertiary_institution_id' => $tertiaryInstitutionId,
                'employment_status' => EmploymentStatusEnum::EMPLOYED->value,
                'gender' => 'Female',
                'date_of_birth' => '1990-05-14',
                'country_of_residence_id' => $nigeria?->id,
                'sector_of_employment' => 'Education',
                'area_of_interest' => ['Scholarships', 'Mentorship'],
                'address' => '14 Allen Avenue, Ikeja, Lagos',
                'status' => 'active',
                'email_verified_at' => now(),
                'onboarded_at' => now(),
                'last_active_at' => now(),
            ]
        );

        $user->syncRoles([eRole::CUSTOMER->value]);
    }

    private function seedOrganization(TertiaryInstitutionRepositoryInterface $institutionRepository, ?Country $nigeria): void
    {
        $tertiaryInstitutionId = $institutionRepository->findOrCreateByName('University of Lagos, Lagos')->id;

        $user = User::firstOrCreate(
            ['email' => 'nhef-organization@yopmail.com'],
            [
                'organisation_name' => 'Lagos Impact Foundation',
                'phone_number' => '198765434',
                'country_code' => '+234',
                '2fa' => false,
                'is_active' => true,
                'can_login' => true,
                'password' => Hash::make(self::PASSWORD),
                'constituent_type' => ConstituentTypeEnum::ORGANIZATION->value,
                'tertiary_institution_id' => $tertiaryInstitutionId,
                'organisation_type' => 'Non-Governmental Organisation',
                'organisation_description' => 'A foundation supporting education and community development across Lagos State.',
                'rc_number' => 'RC1234567',
                'date_of_incorporation' => '2010-03-01',
                'website' => 'https://lagosimpactfoundation.example.test',
                'country_of_operation_id' => $nigeria?->id,
                'organisation_size' => '51-200',
                'sector_of_operation' => 'Non-Profit',
                'engagement_preference' => ['Partnership', 'Sponsorship'],
                'status' => 'active',
                'email_verified_at' => now(),
                'onboarded_at' => now(),
                'last_active_at' => now(),
            ]
        );

        $user->syncRoles([eRole::CUSTOMER->value]);
    }
}
