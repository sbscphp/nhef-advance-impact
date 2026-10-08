<?php

namespace Database\Seeders;

use App\Enums\eRole;
use App\Models\Admin;
use App\Models\Institution;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Test admin users, all with password "password":
 * nhef-admin-1 and nhef-admin-2 (Super Admin), nhef-fme (Ministry of Education), all @yopmail.com, plus
 * nhef-institution-admin@yopmail.com (Institution Admin of University of Lagos).
 *
 * php artisan db:seed --class=AdminSeeder
 */
class AdminSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, InstitutionSeeder::class]);

        $accounts = [
            ['email' => 'nhef-admin-1@yopmail.com', 'name' => 'Super Admin', 'role' => eRole::SUPER_ADMIN, '2fa' => true],
            ['email' => 'nhef-admin-2@yopmail.com', 'name' => 'Super Admin 2', 'role' => eRole::SUPER_ADMIN, '2fa' => false],
            ['email' => 'nhef-fme@yopmail.com', 'name' => 'Ministry of Education', 'role' => eRole::MINISTRY_OF_EDUCATION, '2fa' => false],
            ['email' => 'nhef-institution-admin@yopmail.com', 'name' => 'University of Lagos Admin', 'role' => eRole::INSTITUTION_ADMIN, '2fa' => false, 'institution' => 'University of Lagos'],
        ];

        foreach ($accounts as $row) {
            $admin = Admin::firstOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'can_login' => true,
                    'institution_id' => isset($row['institution'])
                        ? Institution::withoutGlobalScopes()->where('name', $row['institution'])->value('id')
                        : null,
                    'password' => Hash::make(self::PASSWORD),
                    '2fa' => $row['2fa'],
                ]
            );
            $admin->syncRoles([$row['role']->value]);
        }
    }
}
