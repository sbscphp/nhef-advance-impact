<?php

namespace App\Http\Controllers\v1\Admin\Settings;

use App\Enums\AuditActionEnum;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\ToggleTwoFactorRequest;
use App\Http\Requests\Settings\ChangeSettingsPasswordRequest;
use App\Http\Requests\Settings\UpdateAdminProfileRequest;
use App\Http\Requests\Settings\UpdateNotificationPreferencesRequest;
use App\Models\Admin;
use App\Responser\JsonResponser;
use App\Services\Settings\AccountSettingsService;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;

#[Group('Admin Settings', 'Authenticated account settings for admins: profile, two-factor toggle, password change, and notification preferences.')]
class SettingsController extends Controller
{
    public function __construct(private readonly AccountSettingsService $settingsService) {}

    /**
     * Get profile
     *
     * Returns the authenticated admin's profile, roles, and permissions.
     */
    #[Endpoint('Get profile')]
    #[Authenticated]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Profile retrieved.',
        'data' => [
            'uuid' => 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8',
            'name' => 'Jane Officer',
            'email' => 'jane.officer@nhef.org',
            '2fa' => true,
            'is_active' => true,
            'can_login' => true,
            'must_reset_password' => false,
            'email_notifications_enabled' => true,
            'push_notifications_enabled' => true,
            'roles' => ['Fundraising Officer'],
            'permissions' => ['campaigns.read', 'campaigns.create'],
            'last_login_at' => '2026-07-19T08:00:00Z',
            'last_active_at' => '2026-07-20T08:00:00Z',
            'created_at' => '2026-01-01T00:00:00Z',
            'updated_at' => '2026-07-20T08:00:00Z',
        ],
    ], description: 'Admin profile retrieved.')]
    public function profile(Request $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $profile = $this->settingsService->adminProfile($admin);

            return JsonResponser::send(false, 'Profile retrieved.', $profile, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Settings\SettingsController@profile');
        }
    }

    /**
     * Update profile
     *
     * Updates the authenticated admin's display name.
     */
    #[Endpoint('Update profile')]
    #[Authenticated]
    #[BodyParam('name', 'string', 'Display name.', required: true, example: 'Jane Officer')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Profile updated.',
        'data' => ['uuid' => 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8', 'name' => 'Jane Officer'],
    ], description: 'Profile updated.')]
    public function updateProfile(UpdateAdminProfileRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $previousName = (string) $admin->name;
            $newName = (string) $request->validated('name');
            $profile = $this->settingsService->updateAdminProfile($admin, $newName);

            if ($previousName !== $newName) {
                GeneralHelper::storeAuditLog(
                    UserTypeEnum::ADMIN,
                    AuditActionEnum::PROFILE_UPDATED,
                    $request,
                    $admin->uuid,
                    [
                        'previous_name' => $previousName,
                        'new_name' => $newName,
                    ],
                    $newName.' updated their profile name.',
                    Admin::class,
                    $admin->uuid,
                    ModuleEnums::settings,
                    200,
                );
            }

            return JsonResponser::send(false, 'Profile updated.', $profile, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Settings\SettingsController@updateProfile');
        }
    }

    /**
     * Toggle two-factor authentication
     *
     * Flips 2FA on or off for the authenticated admin (no body — it toggles the current state).
     */
    #[Endpoint('Toggle two-factor authentication')]
    #[Authenticated]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Two-factor authentication updated.',
        'data' => ['2fa' => true],
    ], description: '2FA preference updated.')]
    public function toggleTwoFactor(ToggleTwoFactorRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $result = $this->settingsService->toggleAdminTwoFactor($admin, ! (bool) $admin->{'2fa'});

            return JsonResponser::send(false, 'Two-factor authentication updated.', $result, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Settings\SettingsController@toggleTwoFactor');
        }
    }

    /**
     * Change password
     *
     * Sets a new password for the authenticated admin. Requires the current password to
     * confirm the change; the new password may not match the existing one.
     */
    #[Endpoint('Change password')]
    #[Authenticated]
    #[BodyParam('current_password', 'string', 'The admin\'s current password.', required: true, example: 'OldPass1!')]
    #[BodyParam('password', 'string', 'New password: min 8 characters, mixed case, letters, numbers, and symbols.', required: true, example: 'Str0ng!Pass1')]
    #[BodyParam('password_confirmation', 'string', 'Must match `password`.', required: true, example: 'Str0ng!Pass1')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Password changed successfully.',
        'data' => null,
    ], description: 'Password updated.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'The current password is incorrect.',
        'data' => null,
    ], description: 'current_password does not match, new password matches the current one, or fails the password policy.')]
    public function changePassword(ChangeSettingsPasswordRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $this->settingsService->updatePassword(
                $admin,
                (string) $request->input('current_password'),
                (string) $request->input('password')
            );

            return JsonResponser::send(false, 'Password changed successfully.', null, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Settings\SettingsController@changePassword');
        }
    }

    /**
     * Update notification preferences
     *
     * Updates the authenticated admin's email/push notification preferences. At least one of
     * the two fields is required.
     */
    #[Endpoint('Update notification preferences')]
    #[Authenticated]
    #[BodyParam('email_notifications_enabled', 'boolean', 'Receive account/system emails. Required if push_notifications_enabled is omitted.', required: false, example: true)]
    #[BodyParam('push_notifications_enabled', 'boolean', 'Receive push notifications. Required if email_notifications_enabled is omitted.', required: false, example: true)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Notification preferences updated.',
        'data' => ['email_notifications_enabled' => true, 'push_notifications_enabled' => true],
    ], description: 'Notification preferences updated.')]
    public function updateNotificationPreferences(UpdateNotificationPreferencesRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $preferences = $this->settingsService->updateNotificationPreferences($admin, $request->validated());

            return JsonResponser::send(false, 'Notification preferences updated.', $preferences, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Settings\SettingsController@updateNotificationPreferences');
        }
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
