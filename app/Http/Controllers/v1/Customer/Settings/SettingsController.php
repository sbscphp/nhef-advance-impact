<?php

namespace App\Http\Controllers\v1\Customer\Settings;

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

    /**
     * Change password
     *
     * Sets a new password for the authenticated customer. Requires the current password to
     * confirm the change; the new password may not match the existing one.
     */
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
