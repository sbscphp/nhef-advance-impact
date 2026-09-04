<?php

namespace App\Services\Settings;

use App\Enums\ModuleEnums;
use App\Exceptions\ApiException;
use App\Helpers\FileUploadHelper;
use App\Http\Resources\UserResource;
use App\Models\Admin;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use App\Repositories\Contracts\TertiaryInstitution\TertiaryInstitutionRepositoryInterface;
use App\Services\Notifications\NotificationDispatchService;
use App\Support\PasswordRules;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

class AccountSettingsService
{
    /** @var array<string, string> */
    private const PROFILE_FIELD_LABELS = [
        'phone_number' => 'Phone number',
        'country_code' => 'Country code',
        'firstname' => 'First name',
        'lastname' => 'Last name',
        'middlename' => 'Middle name',
        'university' => 'University',
        'year_of_graduation' => 'Year of graduation',
        'profile_picture_url' => 'Profile picture',
    ];

    public function __construct(
        private readonly NotificationDispatchService $notificationDispatchService,
        private readonly TertiaryInstitutionRepositoryInterface $institutionRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function customerProfile(User $user): array
    {
        $user->loadMissing(['roles', 'tertiaryInstitution']);

        return UserResource::make($user)->resolve();
    }

    /**
     * @return array<string, mixed>
     */
    public function adminProfile(Admin $admin): array
    {
        $admin->loadMissing(['roles', 'permissions']);

        return [
            'uuid' => $admin->uuid,
            'name' => $admin->name,
            'email' => $admin->email,
            '2fa' => (bool) $admin->{'2fa'},
            'is_active' => (bool) $admin->is_active,
            'can_login' => (bool) $admin->can_login,
            'must_reset_password' => (bool) $admin->must_reset_password,
            'email_notifications_enabled' => (bool) $admin->email_notifications_enabled,
            'push_notifications_enabled' => (bool) $admin->push_notifications_enabled,
            'roles' => $admin->roles->pluck('name')->values(),
            'permissions' => $admin->getAllPermissions()->pluck('name')->values(),
            'last_login_at' => $admin->last_login_at,
            'last_active_at' => $admin->last_active_at,
            'created_at' => $admin->created_at,
            'updated_at' => $admin->updated_at,
        ];
    }

    public function updatePassword(User|Admin $authenticatable, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, (string) $authenticatable->password)) {
            throw new ApiException('The current password is incorrect.', 422);
        }

        $this->validatePasswordStrength($newPassword);

        if (Hash::check($newPassword, (string) $authenticatable->password)) {
            throw new ApiException('You cannot reuse your current password.', 422);
        }

        $updates = [
            'password' => $newPassword,
        ];

        if ($authenticatable instanceof Admin) {
            $updates['must_reset_password'] = false;
        }

        $authenticatable->forceFill($updates)->save();

        $this->sendPasswordChangedNotification($authenticatable);
    }

    /**
     * @return array<string, mixed>
     */
    public function updateAdminProfile(Admin $admin, string $name): array
    {
        $previousName = (string) $admin->name;
        $newName = trim($name);

        $admin->forceFill(['name' => $newName])->save();

        if ($previousName !== $newName) {
            $this->sendAdminNameChangedNotification($admin, $previousName, $newName);
        }

        return $this->adminProfile($admin->fresh() ?? $admin);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateCustomerProfile(User $user, array $data): array
    {
        $updates = [];

        foreach (['firstname', 'lastname', 'middlename', 'phone_number', 'country_code', 'year_of_graduation'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }

        if (array_key_exists('profile_picture', $data)) {
            $picture = $data['profile_picture'];
            $updates['profile_picture_url'] = ($picture === null || $picture === '')
                ? null
                : FileUploadHelper::smartSingleFileUpload($picture, 'avatars');
        }

        $universityChange = null;
        if (array_key_exists('university', $data)) {
            $universityChange = $this->resolveUniversityUpdate($user, $updates, $data['university']);
        }

        if ($updates === []) {
            throw new ApiException('No profile fields to update.', 422);
        }

        $changes = $this->resolveCustomerProfileChanges($user, Arr::except($updates, ['tertiary_institution_id']));
        if ($universityChange !== null) {
            $changes[] = $universityChange;
        }

        $user->forceFill($updates)->save();

        if ($changes !== []) {
            $this->sendCustomerProfileUpdatedNotification($user, $changes);
        }

        return $this->customerProfile($user->fresh() ?? $user);
    }

    /**
     * @return array{biometrics_enabled: bool}
     */
    public function toggleCustomerBiometrics(User $user, bool $enabled): array
    {
        $user->forceFill(['biometrics_enabled' => $enabled])->save();

        return [
            'biometrics_enabled' => (bool) $user->biometrics_enabled,
        ];
    }

    /**
     * @return array{2fa: bool}
     */
    public function toggleCustomerTwoFactor(User $user, bool $enabled): array
    {
        return $this->toggleTwoFactor($user, $enabled);
    }

    /**
     * @return array{2fa: bool}
     */
    public function toggleAdminTwoFactor(Admin $admin, bool $enabled): array
    {
        return $this->toggleTwoFactor($admin, $enabled);
    }

    /**
     * @return array{2fa: bool}
     */
    private function toggleTwoFactor(User|Admin $authenticatable, bool $enabled): array
    {
        $authenticatable->forceFill(['2fa' => $enabled])->save();

        return [
            '2fa' => (bool) $authenticatable->{'2fa'},
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, bool>
     */
    public function updateNotificationPreferences(User|Admin $authenticatable, array $data): array
    {
        $authenticatable->forceFill([
            'email_notifications_enabled' => (bool) ($data['email_notifications_enabled'] ?? $authenticatable->email_notifications_enabled),
            'push_notifications_enabled' => (bool) ($data['push_notifications_enabled'] ?? $authenticatable->push_notifications_enabled),
        ])->save();

        return [
            'email_notifications_enabled' => (bool) $authenticatable->email_notifications_enabled,
            'push_notifications_enabled' => (bool) $authenticatable->push_notifications_enabled,
        ];
    }

    private function validatePasswordStrength(string $password): void
    {
        validator(
            ['password' => $password],
            ['password' => ['required', 'string', PasswordRules::make()]]
        )->validate();
    }

    private function sendAdminNameChangedNotification(Admin $admin, string $previousName, string $newName): void
    {
        $this->notificationDispatchService->notifyAdminsByUuids(
            [$admin->uuid],
            new GenericDatabaseNotification(
                module: ModuleEnums::settings->value,
                event: 'profile_name_updated',
                title: 'Profile name updated',
                message: sprintf(
                    'Your name was changed from "%s" to "%s".',
                    $previousName,
                    $newName,
                ),
                meta: [
                    'previous_name' => $previousName,
                    'new_name' => $newName,
                    'updated_at' => now()->toIso8601String(),
                ],
                actionUrl: null,
                mailSubject: null,
                icon: '/icons/profile-updated.png',
                severity: 'info',
                tags: ['settings', 'profile'],
                sendMail: false,
                sendPush: false,
            ),
        );
    }

    private function sendPasswordChangedNotification(User|Admin $authenticatable): void
    {
        $notification = new GenericDatabaseNotification(
            module: ModuleEnums::settings->value,
            event: 'password_changed',
            title: 'Password changed',
            message: 'Your password was changed successfully. If you did not make this change, contact support immediately.',
            meta: [
                'changed_at' => now()->toIso8601String(),
            ],
            actionUrl: null,
            mailSubject: null,
            icon: '/icons/password-changed.png',
            severity: 'warning',
            tags: ['settings', 'security', 'password'],
            sendMail: false,
            sendPush: false,
        );

        if ($authenticatable instanceof Admin) {
            $this->notificationDispatchService->notifyAdminsByUuids([$authenticatable->uuid], $notification);

            return;
        }

        $this->notificationDispatchService->notifyUsersByUuids([$authenticatable->uuid], $notification);
    }

    /**
     * @param  list<array{field: string, label: string, from: string|null, to: string|null}>  $changes
     */
    private function sendCustomerProfileUpdatedNotification(User $user, array $changes): void
    {
        $this->notificationDispatchService->notifyUsersByUuids(
            [$user->uuid],
            new GenericDatabaseNotification(
                module: ModuleEnums::settings->value,
                event: 'profile_updated',
                title: 'Profile updated',
                message: $this->buildCustomerProfileUpdatedMessage($changes),
                meta: [
                    'changes' => $changes,
                    'updated_at' => now()->toIso8601String(),
                ],
                actionUrl: null,
                mailSubject: null,
                icon: '/icons/profile-updated.png',
                severity: 'info',
                tags: ['settings', 'profile'],
                sendMail: false,
                sendPush: false,
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $updates
     * @return list<array{field: string, label: string, from: string|null, to: string|null}>
     */
    private function resolveCustomerProfileChanges(User $user, array $updates): array
    {
        $changes = [];

        foreach ($updates as $field => $newValue) {
            $oldValue = $user->getAttribute($field);
            $displayFrom = $this->formatProfileFieldValue($oldValue);
            $displayTo = $this->formatProfileFieldValue($newValue);

            if ($displayFrom === $displayTo) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'label' => self::PROFILE_FIELD_LABELS[$field] ?? str_replace('_', ' ', ucfirst($field)),
                'from' => $displayFrom,
                'to' => $displayTo,
            ];
        }

        return $changes;
    }

    /**
     * Resolves the submitted university name against (or into) the tertiary_institutions
     * reference list, sets `tertiary_institution_id` on $updates, and returns a display-ready
     * change entry (by name, not ID) for the profile-updated notification - or null if unchanged.
     *
     * @param  array<string, mixed>  $updates
     * @return array{field: string, label: string, from: ?string, to: ?string}|null
     */
    private function resolveUniversityUpdate(User $user, array &$updates, mixed $rawValue): ?array
    {
        $oldName = $user->tertiaryInstitution?->name;

        $newInstitution = ($rawValue !== null && trim((string) $rawValue) !== '')
            ? $this->institutionRepository->findOrCreateByName((string) $rawValue)
            : null;

        $updates['tertiary_institution_id'] = $newInstitution?->id;
        $newName = $newInstitution?->name;

        if ($oldName === $newName) {
            return null;
        }

        return [
            'field' => 'tertiary_institution_id',
            'label' => self::PROFILE_FIELD_LABELS['university'],
            'from' => $oldName,
            'to' => $newName,
        ];
    }

    private function formatProfileFieldValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param  list<array{field: string, label: string, from: string|null, to: string|null}>  $changes
     */
    private function buildCustomerProfileUpdatedMessage(array $changes): string
    {
        if ($changes === []) {
            return 'Your profile was updated.';
        }

        if (count($changes) === 1) {
            $change = $changes[0];

            return sprintf(
                'Your %s was changed from "%s" to "%s".',
                strtolower($change['label']),
                $change['from'] ?? 'empty',
                $change['to'] ?? 'empty',
            );
        }

        $details = array_map(
            fn (array $change): string => sprintf(
                '%s changed from "%s" to "%s"',
                $change['label'],
                $change['from'] ?? 'empty',
                $change['to'] ?? 'empty',
            ),
            $changes,
        );

        return 'Your profile was updated: '.implode('; ', $details).'.';
    }
}
