<?php

namespace App\Http\Controllers\v1\Customer\Settings;

use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Settings\CustomerProfileUpdateRequest;
use App\Http\Requests\Customer\Settings\ToggleBiometricsRequest;
use App\Http\Requests\Customer\Settings\ToggleTwoFactorRequest;
use App\Http\Requests\Settings\ChangeSettingsPasswordRequest;
use App\Http\Requests\Settings\UpdateNotificationPreferencesRequest;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Settings\AccountSettingsService;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;

#[Group('Customer Settings', 'Authenticated account settings for customers: profile, two-factor/biometrics toggles, password change, and notification preferences.')]
class SettingsController extends Controller
{
    public function __construct(private readonly AccountSettingsService $settingsService) {}

    #[Endpoint('Get profile')]
    #[Authenticated]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Profile retrieved.',
        'data' => [
            'uuid' => 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'middlename' => null,
            'profile_picture_url' => 'https://res.cloudinary.com/nhef/image/upload/v1/avatars/123_2026-07-20_1700000000.jpg',
            'email' => 'john@email.com',
            'email_verified_at' => '2026-07-15T10:00:00Z',
            'registration_step' => 'completed',
            'phone_number' => '+2340000000',
            'country_code' => '+234',
            'university' => 'Unilag',
            'year_of_graduation' => 2026,
            '2fa' => false,
            'email_notifications_enabled' => true,
            'push_notifications_enabled' => true,
            'biometrics_enabled' => false,
            'is_active' => true,
            'can_login' => true,
            'last_login_at' => '2026-07-19T08:00:00Z',
            'last_active_at' => '2026-07-20T08:00:00Z',
            'role' => ['name' => 'Alumni'],
            'created_at' => '2026-01-01T00:00:00Z',
            'updated_at' => '2026-07-20T08:00:00Z',
        ],
    ], description: 'Customer profile retrieved.')]
    public function profile(Request $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $profile = $this->settingsService->customerProfile($user);

            return JsonResponser::send(false, 'Profile retrieved.', $profile, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Settings\SettingsController@profile');
        }
    }

    #[Endpoint('Update profile')]
    #[Authenticated]
    #[BodyParam('firstname', 'string', 'First name.', required: false, example: 'John')]
    #[BodyParam('lastname', 'string', 'Last name.', required: false, example: 'Doe')]
    #[BodyParam('middlename', 'string', 'Middle name.', required: false, example: null)]
    #[BodyParam('phone_number', 'string', 'Phone number, without country code.', required: false, example: '0000000000')]
    #[BodyParam('country_code', 'string', 'Phone country code prefix, e.g. +234.', required: false, example: '+234')]
    #[BodyParam('university', 'string', 'University attended.', required: false, example: 'Unilag')]
    #[BodyParam('year_of_graduation', 'int', 'Year of graduation.', required: false, example: 2026)]
    #[BodyParam('profile_picture', 'file', 'New profile picture: image file, http(s) URL, or base64/data-URI. Send null/empty to remove the current picture. Max 10MB; JPG/PNG/GIF/WEBP.', required: false, example: null)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Profile updated.',
        'data' => ['uuid' => 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8', 'firstname' => 'John', 'profile_picture_url' => 'https://res.cloudinary.com/nhef/image/upload/v1/avatars/123_2026-07-20_1700000000.jpg'],
    ], description: 'Profile updated.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'The email field is prohibited.',
        'data' => null,
    ], description: 'Attempted to change email, or a field failed validation.')]
    public function updateProfile(CustomerProfileUpdateRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $profile = $this->settingsService->updateCustomerProfile($user, $request->validated());

            return JsonResponser::send(false, 'Profile updated.', $profile, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Settings\SettingsController@updateProfile');
        }
    }

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
            $user = $this->requireCustomer($request);
            $result = $this->settingsService->toggleCustomerTwoFactor($user, ! (bool) $user->{'2fa'});

            return JsonResponser::send(false, 'Two-factor authentication updated.', $result, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Settings\SettingsController@toggleTwoFactor');
        }
    }

    #[Endpoint('Toggle biometrics')]
    #[Authenticated]
    #[BodyParam('biometrics_enabled', 'boolean', 'Explicit value to set; omit to toggle the current state.', required: false, example: true)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Biometrics preference updated.',
        'data' => ['biometrics_enabled' => true],
    ], description: 'Biometrics preference updated.')]
    public function toggleBiometrics(ToggleBiometricsRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $enabled = $request->has('biometrics_enabled')
                ? (bool) $request->boolean('biometrics_enabled')
                : ! (bool) $user->biometrics_enabled;
            $result = $this->settingsService->toggleCustomerBiometrics($user, $enabled);

            return JsonResponser::send(false, 'Biometrics preference updated.', $result, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Settings\SettingsController@toggleBiometrics');
        }
    }

    #[Endpoint('Change password')]
    #[Authenticated]
    #[BodyParam('current_password', 'string', 'The customer\'s current password.', required: true, example: 'OldPass1!')]
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
            $user = $this->requireCustomer($request);
            $this->settingsService->updatePassword(
                $user,
                (string) $request->input('current_password'),
                (string) $request->input('password')
            );

            return JsonResponser::send(false, 'Password changed successfully.', null, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Settings\SettingsController@changePassword');
        }
    }

    #[Endpoint('Update notification preferences')]
    #[Authenticated]
    #[BodyParam('email_notifications_enabled', 'boolean', 'Receive account/donation emails. Required if push_notifications_enabled is omitted.', required: false, example: true)]
    #[BodyParam('push_notifications_enabled', 'boolean', 'Receive push notifications. Required if email_notifications_enabled is omitted.', required: false, example: true)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Notification preferences updated.',
        'data' => ['email_notifications_enabled' => true, 'push_notifications_enabled' => true],
    ], description: 'Notification preferences updated.')]
    public function updateNotificationPreferences(UpdateNotificationPreferencesRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $preferences = $this->settingsService->updateNotificationPreferences($user, $request->validated());

            return JsonResponser::send(false, 'Notification preferences updated.', $preferences, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Settings\SettingsController@updateNotificationPreferences');
        }
    }

    private function requireCustomer(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403, 'Forbidden.');
        }

        return $user;
    }
}
