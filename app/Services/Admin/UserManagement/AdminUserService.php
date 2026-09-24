<?php

namespace App\Services\Admin\UserManagement;

use App\Enums\AuditActionEnum;
use App\Enums\eRole;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Jobs\SendAdminInviteSetPasswordEmailJob;
use App\Models\Admin;
use App\Models\Institution;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminUserService
{
    private const MAX_EXPORT_ROWS = 5000;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, Admin $actor, Request $request): Admin
    {
        $roleUuid = (string) $payload['role_id'];

        $role = Role::query()
            ->where('guard_name', 'api')
            ->where('uuid', $roleUuid)
            ->firstOrFail();

        $this->assertRoleAssignable($role);

        $frontendUrl = isset($payload['frontend_url']) && is_string($payload['frontend_url'])
            ? $payload['frontend_url']
            : null;

        $institutionId = Institution::current()?->id;

        $admin = DB::transaction(function () use ($payload, $role, $institutionId): Admin {
            $admin = Admin::query()->create([
                'name' => (string) $payload['name'],
                'email' => (string) $payload['email'],
                'job_title' => $payload['job_title'] ?? null,
                'institution_id' => $institutionId,
                // Random placeholder; admin sets real password via emailed invite link.
                'password' => bin2hex(random_bytes(16)),
                'is_active' => (bool) ($payload['is_active'] ?? true),
                'can_login' => (bool) ($payload['can_login'] ?? true),
                'must_reset_password' => true,
            ]);

            $admin->syncRoles([$role->name]);

            return $admin;
        });

        $this->queueInviteResetLink($admin, $frontendUrl);

        $admin = $admin->fresh() ?? $admin;

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::ADMIN_CREATED,
            $request,
            $actor->uuid,
            [
                'admin_uuid' => $admin->uuid,
                'admin_email' => $admin->email,
                'role_name' => $role->name,
            ],
            'Admin user created.',
            Admin::class,
            $admin->uuid,
            ModuleEnums::user_management,
            200,
        );

        return $admin;
    }

    public function resendInviteResetLink(string $adminId, Admin $actor, Request $request): Admin
    {
        $admin = $this->resolveAdmin($adminId);

        if (! (bool) $admin->must_reset_password) {
            throw new ApiException('Reset link can only be resent for admins pending first-time password setup.', 422);
        }

        $this->queueInviteResetLink($admin);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::ADMIN_INVITE_LINK_RESENT,
            $request,
            $actor->uuid,
            ['admin_uuid' => $admin->uuid, 'admin_email' => $admin->email],
            'Admin invite password setup link resent.',
            Admin::class,
            $admin->uuid,
            ModuleEnums::user_management,
            200,
        );

        return $admin;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{total:int,active:int,inactive:int}
     */
    public function stats(array $validated): array
    {
        $query = $this->visibleAdmins();
        ListingFilterRules::applyResolvedDateRange($query, $validated, 'created_at');

        return array_merge(ListingFilterRules::periodMeta($validated), [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('is_active', true)->count(),
            'inactive' => (clone $query)->where('is_active', false)->count(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function list(array $validated): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($validated['per_page'] ?? 15), 100));

        return $this->baseListQuery($validated)->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: Collection<int, Admin>, 1: bool}
     */
    public function exportCollection(array $validated): array
    {
        $query = $this->baseListQuery($validated);
        $total = (clone $query)->count();
        $truncated = $total > self::MAX_EXPORT_ROWS;
        /** @var Collection<int, Admin> $rows */
        $rows = $query->limit(self::MAX_EXPORT_ROWS)->get();

        return [$rows, $truncated];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function baseListQuery(array $validated): Builder
    {
        $sortBy = (string) ($validated['sort_by'] ?? 'created_at');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = $this->visibleAdmins()->with('roles:id,name');
        ListingFilterRules::applyResolvedDateRange($query, $validated, 'created_at');

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '') {
            $uuidSearch = str_starts_with(strtoupper($search), 'NHF-USR-') ? strtolower(substr($search, 8)) : $search;
            $query->where(function (Builder $builder) use ($search, $uuidSearch): void {
                $builder->where('uuid', 'like', '%'.$uuidSearch.'%')
                    ->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhereHas('roles', fn (Builder $roleBuilder) => $roleBuilder->where('name', 'like', '%'.$search.'%'));
            });
        }

        $status = data_get($validated, 'filters.status');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $roleUuid = data_get($validated, 'filters.role_id');
        if (is_string($roleUuid) && $roleUuid !== '') {
            $query->whereHas('roles', fn (Builder $roleBuilder) => $roleBuilder->where('roles.uuid', $roleUuid));
        }

        if (! in_array($sortBy, ['uuid', 'name', 'email', 'last_active_at', 'is_active', 'created_at'], true)) {
            $sortBy = 'created_at';
        }

        return $query->orderBy($sortBy, $sortDirection);
    }

    public function findAdmin(string $adminId): Admin
    {
        return $this->resolveAdmin($adminId);
    }

    /**
     * @return Collection<int, Admin>
     */
    public function dropdown(string $status = 'active'): Collection
    {
        $query = $this->visibleAdmins();

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        return $query
            ->orderBy('name')
            ->get(['uuid', 'name', 'email', 'is_active']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(string $adminId, array $payload, Admin $actor, Request $request): Admin
    {
        $admin = $this->resolveAdmin($adminId);
        $admin->loadMissing('roles:id,name');
        $previousRoleName = $admin->roles->first()?->name;
        $previous = [
            'name' => $admin->name,
            'email' => $admin->email,
            'job_title' => $admin->job_title,
            'is_active' => (bool) $admin->is_active,
            'can_login' => (bool) $admin->can_login,
            'role_name' => $previousRoleName,
        ];

        $roleUuid = $payload['role_id'] ?? null;
        unset($payload['role_id']);

        $losesAccess = (array_key_exists('is_active', $payload) && ! $payload['is_active'])
            || (array_key_exists('can_login', $payload) && ! $payload['can_login']);
        if ($losesAccess) {
            $this->assertNotLastSuperAdmin($admin);
        }

        if ($payload !== []) {
            $admin->fill($payload)->save();
        }

        $newRoleName = $previousRoleName;
        if ($roleUuid !== null) {
            $role = Role::query()
                ->where('guard_name', 'api')
                ->where('uuid', (string) $roleUuid)
                ->firstOrFail();
            $this->assertRoleAssignable($role);
            if ($role->name !== $previousRoleName) {
                $this->assertNotLastSuperAdmin($admin);
            }
            $admin->syncRoles([$role->name]);
            $newRoleName = $role->name;
        }

        $admin = $admin->fresh() ?? $admin;
        $admin->loadMissing('roles:id,name');

        $changedFields = [];
        foreach (['name', 'email', 'job_title', 'is_active', 'can_login'] as $field) {
            if (array_key_exists($field, $payload) && $previous[$field] !== $admin->{$field}) {
                $changedFields[] = $field;
            }
        }
        if ($roleUuid !== null && $previousRoleName !== $newRoleName) {
            $changedFields[] = 'role';
        }

        if ($changedFields !== []) {
            $metadata = [
                'admin_uuid' => $admin->uuid,
                'admin_email' => $admin->email,
                'fields' => $changedFields,
            ];
            if (in_array('role', $changedFields, true)) {
                $metadata['previous_role'] = $previousRoleName;
                $metadata['new_role'] = $newRoleName;
            }

            GeneralHelper::storeAuditLog(
                UserTypeEnum::ADMIN,
                AuditActionEnum::ADMIN_UPDATED,
                $request,
                $actor->uuid,
                $metadata,
                'Admin user updated.',
                Admin::class,
                $admin->uuid,
                ModuleEnums::user_management,
                200,
            );
        }

        return $admin;
    }

    public function toggleActiveStatus(string $adminId, Admin $actor, Request $request): Admin
    {
        $admin = $this->resolveAdmin($adminId);
        $previousStatus = (bool) $admin->is_active;
        $isActive = ! $previousStatus;

        if (! $isActive) {
            $this->assertNotLastSuperAdmin($admin);
        }

        $admin->forceFill([
            'is_active' => $isActive,
            'can_login' => $isActive,
        ])->save();

        $admin = $admin->fresh() ?? $admin;

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::ADMIN_STATUS_TOGGLED,
            $request,
            $actor->uuid,
            [
                'admin_uuid' => $admin->uuid,
                'admin_email' => $admin->email,
                'previous_status' => $previousStatus ? 'active' : 'inactive',
                'new_status' => $isActive ? 'active' : 'inactive',
            ],
            $isActive ? 'Admin user activated.' : 'Admin user deactivated.',
            Admin::class,
            $admin->uuid,
            ModuleEnums::user_management,
            200,
        );

        return $admin;
    }

    /**
     * Soft delete: the row stays so audit entries and records the admin authored keep their name.
     */
    public function delete(string $adminId, Admin $actor, Request $request): void
    {
        $admin = $this->resolveAdmin($adminId);
        $this->assertNotLastSuperAdmin($admin);

        $adminUuid = $admin->uuid;
        $adminEmail = $admin->email;
        $admin->tokens()->delete();
        $admin->forceFill(['is_active' => false, 'can_login' => false])->save();
        $admin->delete();

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::ADMIN_DELETED,
            $request,
            $actor->uuid,
            ['admin_uuid' => $adminUuid, 'admin_email' => $adminEmail],
            'Admin user deleted.',
            Admin::class,
            $adminUuid,
            ModuleEnums::user_management,
            200,
        );
    }

    private function assertNotLastSuperAdmin(Admin $admin): void
    {
        if (! $admin->hasRole(eRole::SUPER_ADMIN->value) || ! $admin->is_active) {
            return;
        }

        $otherActive = Admin::query()
            ->nhefStaff()
            ->whereKeyNot($admin->getKey())
            ->where('is_active', true)
            ->whereHas('roles', fn (Builder $roles) => $roles->where('name', eRole::SUPER_ADMIN->value))
            ->exists();

        if (! $otherActive) {
            throw new ApiException('At least one active Super Admin must remain.', 422);
        }
    }

    private function assertRoleAssignable(Role $role): void
    {
        $isInstitutionRole = in_array($role->name, eRole::institutionAssignable(), true);

        if (Institution::checkCurrent() && ! $isInstitutionRole) {
            throw new ApiException('This role cannot be assigned to an institution admin.', 403);
        }

        if (! Institution::checkCurrent() && $isInstitutionRole) {
            throw new ApiException('The Institution Admin role is assigned through institution onboarding.', 422);
        }
    }

    /**
     * NHEF (no current tenant) manages its own staff here; an institution's admins are
     * confined to that institution by the tenant scope.
     *
     * @return Builder<Admin>
     */
    private function visibleAdmins(): Builder
    {
        return Admin::query()->when(! Institution::checkCurrent(), fn (Builder $query) => $query->nhefStaff());
    }

    private function resolveAdmin(string $adminId): Admin
    {
        return Admin::query()
            ->where('uuid', $adminId)
            ->orWhere('id', is_numeric($adminId) ? (int) $adminId : -1)
            ->firstOrFail();
    }

    private function queueInviteResetLink(Admin $admin, ?string $frontendUrl = null): void
    {
        SendAdminInviteSetPasswordEmailJob::dispatch($admin->uuid, $frontendUrl);
    }
}
