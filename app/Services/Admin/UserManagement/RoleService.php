<?php

namespace App\Services\Admin\UserManagement;

use App\Enums\AdminScopeEnum;
use App\Enums\AuditActionEnum;
use App\Enums\eRole;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Helpers\PermissionModuleMapper;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Admin;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class RoleService
{
    private const MAX_EXPORT_ROWS = 5000;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, Admin $actor, Request $request): Role
    {
        $permissions = $payload['permissions'] ?? null;
        if (is_array($permissions)) {
            $this->assertPermissionsWithinActorGrant($permissions, $actor);
        }

        $role = Role::query()->create([
            'name' => (string) $payload['name'],
            'guard_name' => 'api',
            // An institution's own role is theirs alone; NHEF never creates institution-owned
            // roles (they only hand out the shared system roles).
            'institution_id' => AdminScopeEnum::of($actor) === AdminScopeEnum::INSTITUTION ? $actor->institution_id : null,
            'description' => $payload['description'] ?? null,
            'is_active' => (bool) ($payload['is_active'] ?? true),
        ]);

        if (is_array($permissions)) {
            $role->syncPermissions($permissions);
        }

        $role = $role->fresh() ?? $role;

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::ROLE_CREATED,
            $request,
            $actor->uuid,
            [
                'role_uuid' => $role->uuid,
                'role_name' => $role->name,
                'permissions_count' => is_array($permissions) ? count($permissions) : 0,
            ],
            'Role created.',
            Role::class,
            $role->uuid,
            ModuleEnums::user_management,
            200,
        );

        return $role;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{total:int,active:int,inactive:int}
     */
    public function stats(array $validated, Admin $actor): array
    {
        $query = Role::query()
            ->where('guard_name', 'api')
            ->where('name', '!=', eRole::CUSTOMER->value);
        $this->applyViewScope($query, $actor);
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
    public function list(array $validated, Admin $actor): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($validated['per_page'] ?? 15), 100));

        return $this->baseListQuery($validated, $actor)->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: Collection<int, Role>, 1: bool}
     */
    public function exportCollection(array $validated, Admin $actor): array
    {
        $query = $this->baseListQuery($validated, $actor);
        $total = (clone $query)->count();
        $truncated = $total > self::MAX_EXPORT_ROWS;
        /** @var Collection<int, Role> $rows */
        $rows = $query->limit(self::MAX_EXPORT_ROWS)->get();

        return [$rows, $truncated];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function baseListQuery(array $validated, Admin $actor): Builder
    {
        $sortBy = (string) ($validated['sort_by'] ?? 'updated_at');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = Role::query()
            ->where('guard_name', 'api')
            ->where('name', '!=', eRole::CUSTOMER->value)
            ->withCount('admins as users_count');
        $this->applyViewScope($query, $actor);
        ListingFilterRules::applyResolvedDateRange($query, $validated, 'created_at');

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('uuid', 'like', '%'.$this->uuidFragment($search, 'NHF-RL-').'%');
            });
        }

        $status = data_get($validated, 'filters.status');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if (! in_array($sortBy, ['id', 'name', 'users_count', 'updated_at', 'is_active'], true)) {
            $sortBy = 'updated_at';
        }

        return $query->orderBy($sortBy, $sortDirection);
    }

    public function findRole(string $roleId, Admin $actor): Role
    {
        $role = $this->resolveRole($roleId);
        $this->assertCanView($role, $actor);

        return $role;
    }

    /**
     * @return Collection<int, Role>
     */
    public function listWithPermissions(Admin $actor): Collection
    {
        $query = Role::query()
            ->where('guard_name', 'api')
            ->where('name', '!=', eRole::CUSTOMER->value)
            ->with('permissions:id,name');
        $this->applyViewScope($query, $actor);

        return $query->orderBy('name')->get();
    }

    /**
     * @return array{
     *     permissions: list<string>,
     *     permissions_by_module: list<array{key: string, label: string, permissions: list<array{name: string}>}>,
     *     permission_matrix: list<array<string, mixed>>,
     *     visibility_toggles: list<array{name: string, label: string, granted: bool}>
     * }
     */
    public function listAllPermissions(): array
    {
        $permissionsByModule = PermissionModuleMapper::groupedApiPermissions();
        $permissions = [];

        foreach ($permissionsByModule as $module) {
            foreach ($module['permissions'] as $permission) {
                $permissions[] = $permission['name'];
            }
        }

        return [
            'permissions' => $permissions,
            'permissions_by_module' => $permissionsByModule,
            'permission_matrix' => PermissionModuleMapper::matrix(),
            'visibility_toggles' => PermissionModuleMapper::visibilityToggles(),
        ];
    }

    /**
     * The "assign a role" picker: NHEF is offered its own system roles, never "Institution
     * Admin"; an institution is offered only its own custom roles, never the shared
     * "Institution Admin" role itself, which only a Super Admin may hand out.
     *
     * @return Collection<int, Role>
     */
    public function dropdown(string $status, Admin $actor): Collection
    {
        $query = Role::query()
            ->where('guard_name', 'api')
            ->where('name', '!=', eRole::CUSTOMER->value);

        if (AdminScopeEnum::of($actor) === AdminScopeEnum::INSTITUTION) {
            // Only its own custom roles - "Institution Admin" itself is never assignable by an
            // institution, only by a Super Admin, so it's deliberately left out of this picker.
            $query->where('institution_id', $actor->institution_id);
        } else {
            AdminScopeEnum::NHEF->constrainRoles($query);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        return $query
            ->orderBy('name')
            ->get(['uuid', 'name', 'is_active']);
    }

    /**
     * @return array<string, mixed>
     */
    public function view(string $roleId): array
    {
        $role = $this->resolveRole($roleId);
        $role->load('permissions:id,name');
        $permissionNames = $role->permissions->pluck('name')->values()->all();

        return [
            'role_id' => $role->uuid ?? (string) $role->id,
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'status' => $role->is_active ? 'active' : 'inactive',
            'permissions' => $permissionNames,
            'permissions_by_module' => PermissionModuleMapper::groupedApiPermissionsForNames($permissionNames),
            'updated_at' => $role->updated_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(string $roleId, array $payload, Admin $actor, Request $request): Role
    {
        $role = $this->resolveRole($roleId);
        $this->assertCanManage($role, $actor);
        $role->loadMissing('permissions:id,name');
        $previous = [
            'name' => $role->name,
            'description' => $role->description,
            'is_active' => (bool) $role->is_active,
            'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
        ];

        $permissions = $payload['permissions'] ?? null;
        $reassignRoleId = $payload['reassign_to_role_id'] ?? null;
        unset($payload['permissions'], $payload['reassign_to_role_id']);

        if (array_key_exists('name', $payload) && $payload['name'] !== $role->name) {
            $this->assertNotSystemRole($role, 'A system role cannot be renamed.');
        }

        if (is_array($permissions) && $role->name === eRole::SUPER_ADMIN->value) {
            throw new ApiException('The Super Admin permissions are fixed and cannot be edited.', 422);
        }

        if (is_array($permissions)) {
            $this->assertPermissionsWithinActorGrant($permissions, $actor);
        }

        $deactivating = array_key_exists('is_active', $payload) && (bool) $role->is_active && ! (bool) $payload['is_active'];
        $reassigned = 0;
        if ($deactivating) {
            $this->assertCanDeactivate($role);
            $reassigned = $this->reassignUsers($role, $reassignRoleId, 'deactivated', $actor);
        }

        if ($payload !== []) {
            $role->fill($payload)->save();
        }

        if (is_array($permissions)) {
            $role->syncPermissions($permissions);
        }

        $role = $role->fresh() ?? $role;
        $role->loadMissing('permissions:id,name');

        $changedFields = [];
        foreach (['name', 'description', 'is_active'] as $field) {
            if (array_key_exists($field, $payload) && $previous[$field] !== $role->{$field}) {
                $changedFields[] = $field;
            }
        }
        if (is_array($permissions)) {
            $newPermissions = $role->permissions->pluck('name')->sort()->values()->all();
            if ($previous['permissions'] !== $newPermissions) {
                $changedFields[] = 'permissions';
            }
        }

        if ($changedFields !== []) {
            $metadata = [
                'role_uuid' => $role->uuid,
                'role_name' => $role->name,
                'fields' => $changedFields,
                'users_reassigned' => $reassigned,
            ];
            if (in_array('permissions', $changedFields, true)) {
                $metadata['previous_permissions_count'] = count($previous['permissions']);
                $metadata['new_permissions_count'] = $role->permissions->count();
            }

            GeneralHelper::storeAuditLog(
                UserTypeEnum::ADMIN,
                AuditActionEnum::ROLE_UPDATED,
                $request,
                $actor->uuid,
                $metadata,
                'Role updated.',
                Role::class,
                $role->uuid,
                ModuleEnums::user_management,
                200,
            );
        }

        return $role;
    }

    public function toggleActiveStatus(string $roleId, Admin $actor, Request $request, ?string $reassignRoleId = null): Role
    {
        $role = $this->resolveRole($roleId);
        $this->assertCanManage($role, $actor);
        $previousStatus = (bool) $role->is_active;
        $isActive = ! $previousStatus;
        $reassigned = 0;

        if (! $isActive) {
            $this->assertCanDeactivate($role);
            $reassigned = $this->reassignUsers($role, $reassignRoleId, 'deactivated', $actor);
        }

        $role->forceFill(['is_active' => $isActive])->save();

        $role = $role->fresh() ?? $role;

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::ROLE_STATUS_TOGGLED,
            $request,
            $actor->uuid,
            [
                'role_uuid' => $role->uuid,
                'role_name' => $role->name,
                'previous_status' => $previousStatus ? 'active' : 'inactive',
                'new_status' => $isActive ? 'active' : 'inactive',
                'users_reassigned' => $reassigned,
            ],
            $isActive ? 'Role activated.' : 'Role deactivated.',
            Role::class,
            $role->uuid,
            ModuleEnums::user_management,
            200,
        );

        return $role;
    }

    public function delete(string $roleId, Admin $actor, Request $request, ?string $reassignRoleId = null): void
    {
        $role = $this->resolveRole($roleId);
        $this->assertCanManage($role, $actor);
        $this->assertNotSystemRole($role, 'A system role cannot be deleted.');

        $reassigned = $this->reassignUsers($role, $reassignRoleId, 'deleted', $actor);
        $roleUuid = $role->uuid;
        $roleName = $role->name;
        $role->delete();

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::ROLE_DELETED,
            $request,
            $actor->uuid,
            ['role_uuid' => $roleUuid, 'role_name' => $roleName, 'users_reassigned' => $reassigned],
            'Role deleted.',
            Role::class,
            $roleUuid,
            ModuleEnums::user_management,
            200,
        );
    }

    /**
     * Moves every admin off $role onto the chosen role before the role is deactivated or deleted;
     * refuses (rather than guessing a fallback) when users are assigned and no target was given.
     */
    private function reassignUsers(Role $role, ?string $reassignRoleId, string $action, Admin $actor): int
    {
        $admins = $role->admins()->get();

        if ($admins->isEmpty()) {
            return 0;
        }

        if (blank($reassignRoleId)) {
            throw new ApiException(
                'Role cannot be '.$action.' while '.$admins->count().' admin user(s) are assigned to it. Choose a role to reassign them to.',
                422,
                ['admin_users_count' => $admins->count()],
            );
        }

        $target = $this->resolveRole($reassignRoleId);

        if ($target->is($role) || ! $target->is_active) {
            throw new ApiException('Choose a different, active role to reassign users to.', 422);
        }

        // Same split as AdminUserService::assertRoleAssignable(): allowsRole() alone would let an
        // institution reassign its own admins onto "Institution Admin", the self-escalation this closes.
        if (AdminScopeEnum::current() === AdminScopeEnum::INSTITUTION && $target->name === eRole::INSTITUTION_ADMIN->value) {
            throw new ApiException('The Institution Admin role can only be assigned by a Super Admin. Choose a custom role to reassign to instead.', 403);
        }

        if (! AdminScopeEnum::current()->allowsRole($target->name) && ! ($target->institution_id === $actor->institution_id)) {
            throw new ApiException('This role cannot be used as a reassignment target.', 422);
        }

        if (in_array($target->name, eRole::superAdminAssignable(), true) && ! $actor->hasRole(eRole::SUPER_ADMIN->value)) {
            throw new ApiException('Only a Super Admin can assign the '.$target->name.' role.', 403);
        }

        foreach ($admins as $admin) {
            $admin->syncRoles([$target->name]);
        }

        return $admins->count();
    }

    private function assertNotSystemRole(Role $role, string $message): void
    {
        if (in_array($role->name, eRole::values(), true)) {
            throw new ApiException($message, 422);
        }
    }

    /**
     * NHEF sees every role (oversight). An institution sees only roles it created, plus
     * "Institution Admin" as a read-only reference (never returned to a management call, see
     * {@see self::assertCanManage()}).
     */
    private function applyViewScope(Builder $query, Admin $actor): void
    {
        if (AdminScopeEnum::of($actor) !== AdminScopeEnum::INSTITUTION) {
            return;
        }

        $query->where(fn (Builder $q) => $q
            ->where('institution_id', $actor->institution_id)
            ->orWhere('name', eRole::INSTITUTION_ADMIN->value));
    }

    private function assertCanView(Role $role, Admin $actor): void
    {
        if (AdminScopeEnum::of($actor) !== AdminScopeEnum::INSTITUTION) {
            return;
        }

        if ($role->institution_id !== $actor->institution_id && $role->name !== eRole::INSTITUTION_ADMIN->value) {
            throw new ApiException('You do not have access to this role.', 403);
        }
    }

    /**
     * Stricter than {@see self::assertCanView()}: an institution may only manage roles it
     * created itself. Every system role, "Institution Admin" included, stays Super-Admin-only.
     */
    private function assertCanManage(Role $role, Admin $actor): void
    {
        if (AdminScopeEnum::of($actor) !== AdminScopeEnum::INSTITUTION) {
            return;
        }

        if ($role->institution_id !== $actor->institution_id) {
            throw new ApiException('You can only manage roles your own institution created.', 403);
        }
    }

    /**
     * Nobody may hand out a permission they do not themselves hold - applies to every actor, not
     * just institution admins, since it is sound regardless of scope.
     *
     * @param  list<string>  $permissionNames
     */
    private function assertPermissionsWithinActorGrant(array $permissionNames, Admin $actor): void
    {
        $granted = $actor->getAllPermissions()->pluck('name')->all();
        $disallowed = array_values(array_diff($permissionNames, $granted));

        if ($disallowed !== []) {
            throw new ApiException(
                'You cannot grant permissions you do not hold: '.implode(', ', $disallowed),
                403,
            );
        }
    }

    private function assertCanDeactivate(Role $role): void
    {
        if (in_array($role->name, [eRole::SUPER_ADMIN->value, eRole::INSTITUTION_ADMIN->value], true)) {
            throw new ApiException('This system role cannot be deactivated.', 422);
        }
    }

    private function uuidFragment(string $search, string $codePrefix): string
    {
        return str_starts_with(strtoupper($search), $codePrefix)
            ? strtolower(substr($search, strlen($codePrefix)))
            : $search;
    }

    private function resolveRole(string $roleId): Role
    {
        $role = Role::query()
            ->where('guard_name', 'api')
            ->where(function (Builder $builder) use ($roleId): void {
                $builder->where('uuid', $roleId);
                if (is_numeric($roleId)) {
                    $builder->orWhere('id', (int) $roleId);
                }
            })
            ->first();

        if ($role === null) {
            throw (new ModelNotFoundException)->setModel(Role::class, [$roleId]);
        }

        return $role;
    }
}
