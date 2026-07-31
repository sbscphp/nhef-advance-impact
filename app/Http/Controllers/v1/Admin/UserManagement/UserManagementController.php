<?php

namespace App\Http\Controllers\v1\Admin\UserManagement;

use App\Enums\eRole;
use App\Helpers\GeneralHelper;
use App\Helpers\PDFReportHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DateRangeStatsRequest;
use App\Http\Requests\Admin\UserManagement\AdminListRequest;
use App\Http\Requests\Admin\UserManagement\CreateAdminRequest;
use App\Http\Requests\Admin\UserManagement\CreateRoleRequest;
use App\Http\Requests\Admin\UserManagement\RoleListRequest;
use App\Http\Requests\Admin\UserManagement\UpdateAdminRequest;
use App\Http\Requests\Admin\UserManagement\UpdateRoleRequest;
use App\Http\Resources\Admin\AdminFullResource;
use App\Http\Resources\Admin\AdminListResource;
use App\Http\Resources\Admin\RoleListResource;
use App\Http\Resources\Admin\RoleResource;
use App\Models\Admin;
use App\Models\Role;
use App\Responser\JsonResponser;
use App\Services\Admin\UserManagement\AdminUserService;
use App\Services\Admin\UserManagement\RoleService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Group('Admin User Management', "Create, invite, list, and manage admin accounts and roles. Creating an admin (below) IS how an invite is sent: it creates the account with a placeholder password and `must_reset_password: true`, then emails a `Create Your Password` link. There's no separate send-invite call; see `POST /reset-password/context` and `POST /reset-password` in the Admin Auth / Forgot Password group for what the invitee does with that link.")]
class UserManagementController extends Controller
{
    public function __construct(
        private readonly AdminUserService $adminUserService,
        private readonly RoleService $roleService,
        private readonly PDFReportHelper $pdfReportHelper,
    ) {}

    #[Endpoint('Admin user stats', 'Counts backing the "All Users / Active / Deactivated" overview tiles on the Users tab. Requires the `admins.read` permission.')]
    #[Authenticated]
    #[QueryParam('period', 'string', 'Relative date window; use `custom` with `start_date`/`end_date` for an explicit range.', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Range start (required if `period=custom`).', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'Range end (required if `period=custom`).', required: false, example: '2026-01-30')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Admin stats retrieved.',
        'data' => ['period' => '30days', 'start_date' => '2026-06-29', 'end_date' => '2026-07-29', 'total' => 84, 'active' => 60, 'inactive' => 24],
    ], description: 'Counts scoped to the requested date window (all-time if no period/dates given).')]
    public function adminStats(DateRangeStatsRequest $request)
    {
        try {
            return JsonResponser::send(false, 'Admin stats retrieved.', $this->adminUserService->stats($request->validated()));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\UserManagement\UserManagementController@adminStats');
        }
    }

    #[Endpoint('Role stats', 'Counts backing the "Total / Active / Inactive" overview tiles on the Roles tab. Requires the `roles.read` permission.')]
    #[Authenticated]
    #[QueryParam('period', 'string', 'Relative date window; use `custom` with `start_date`/`end_date` for an explicit range.', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Range start (required if `period=custom`).', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'Range end (required if `period=custom`).', required: false, example: '2026-01-30')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Role stats retrieved.',
        'data' => ['period' => '30days', 'start_date' => '2026-06-29', 'end_date' => '2026-07-29', 'total' => 6, 'active' => 5, 'inactive' => 1],
    ], description: 'Counts scoped to the requested date window (all-time if no period/dates given).')]
    public function roleStats(DateRangeStatsRequest $request)
    {
        try {
            return JsonResponser::send(false, 'Role stats retrieved.', $this->roleService->stats($request->validated()));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\UserManagement\UserManagementController@roleStats');
        }
    }

    #[Endpoint('Create a role', 'Defines a new role and its permission set. Requires the `roles.create` permission.')]
    #[Authenticated]
    #[BodyParam('name', 'string', 'Role name; must be unique.', required: true, example: 'Fundraising Officer')]
    #[BodyParam('description', 'string', 'What the role is for.', required: false, example: 'Manages campaigns, pledges, and donation monitoring.')]
    #[BodyParam('is_active', 'boolean', 'Whether the role is assignable immediately. Defaults to true.', required: false, example: true)]
    #[BodyParam('permissions', 'string[]', 'Permission names to grant; from `GET /permissions`.', required: false, example: ['campaigns.read', 'campaigns.create'])]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Role created successfully.',
        'data' => [
            'role_id' => 'b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e',
            'name' => 'Fundraising Officer',
            'description' => 'Manages campaigns, pledges, and donation monitoring.',
            'status' => 'active',
            'permissions' => ['campaigns.read', 'campaigns.create'],
            'permissions_by_module' => [['key' => 'user_management', 'label' => 'User Management', 'permissions' => [['name' => 'campaigns.read']]]],
            'updated_at' => '2026-07-29T09:00:00.000000Z',
        ],
    ], description: 'Role created.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'A role with this name already exists.',
        'data' => null,
    ], description: 'Validation error.')]
    public function createRole(CreateRoleRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $role = $this->roleService->create($request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Role created successfully.', RoleResource::make($role)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\UserManagement\UserManagementController@createRole');
        }
    }

    #[Endpoint('List roles', 'Paginated, searchable, sortable role listing for the Roles tab. Requires the `roles.read` permission. Pass `export=csv` or `export=pdf` to get a file download instead of this JSON shape.')]
    #[Authenticated]
    #[QueryParam('search', 'string', 'Matches role name, description, or UUID.', required: false, example: 'fundraising')]
    #[QueryParam('filters[status]', 'string', 'Filter by status.', required: false, example: 'active')]
    #[QueryParam('sort_by', 'string', 'One of: id, name, users_count, updated_at, is_active.', required: false, example: 'updated_at')]
    #[QueryParam('sort_direction', 'string', 'asc or desc.', required: false, example: 'desc')]
    #[QueryParam('page', 'integer', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Rows per page (max 100).', required: false, example: 15)]
    #[QueryParam('export', 'string', 'Set to `csv` or `pdf` to download instead of receiving JSON.', required: false, example: 'csv')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Roles retrieved.',
        'data' => [
            'current_page' => 1,
            'per_page' => 15,
            'total' => 2,
            'last_page' => 1,
            'data' => [
                ['role_id' => 'b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e', 'name' => 'Fundraising Officer', 'number_of_users' => 3, 'status' => 'active', 'last_updated' => '2026-07-29T09:00:00.000000Z'],
                ['role_id' => 'c3d4e5f6-a7b8-4c9d-0e1f-2a3b4c5d6e7f', 'name' => 'Super Admin', 'number_of_users' => 1, 'status' => 'active', 'last_updated' => '2026-07-01T09:00:00.000000Z'],
            ],
        ],
    ], description: 'Standard Laravel paginator shape.')]
    public function roleList(RoleListRequest $request)
    {
        try {
            $listing = $request->validated();
            $export = $listing['export'] ?? null;

            return match ($export) {
                'csv' => $this->respondRoleCsv($listing),
                'pdf' => $this->respondRolePdf($listing),
                default => JsonResponser::send(false, 'Roles retrieved.', $this->paginatedPayload($this->roleService->list($listing), RoleListResource::class)),
            };
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\UserManagement\UserManagementController@roleList');
        }
    }

    #[Endpoint('List admin users', 'Paginated, searchable, sortable admin listing backing the "All Users" table. Requires the `admins.read` permission. Pass `export=csv` or `export=pdf` to get a file download instead of this JSON shape.')]
    #[Authenticated]
    #[QueryParam('search', 'string', 'Matches name, email, UUID, or role name.', required: false, example: 'kibrahim')]
    #[QueryParam('filters[status]', 'string', 'Filter by status.', required: false, example: 'active')]
    #[QueryParam('sort_by', 'string', 'One of: uuid, name, email, last_active_at, is_active, created_at.', required: false, example: 'created_at')]
    #[QueryParam('sort_direction', 'string', 'asc or desc.', required: false, example: 'desc')]
    #[QueryParam('page', 'integer', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Rows per page (max 100).', required: false, example: 15)]
    #[QueryParam('export', 'string', 'Set to `csv` or `pdf` to download instead of receiving JSON.', required: false, example: 'csv')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Admin users retrieved.',
        'data' => [
            'current_page' => 1,
            'per_page' => 15,
            'total' => 84,
            'last_page' => 6,
            'data' => [
                ['admin_id' => 'a1b2c3d4-e5f6-4789-a0b1-c2d3e4f5a6b7', 'name' => 'Kelechi Ibrahim', 'role' => 'Super Admin', 'email' => 'kibrahim@sbsc.com', 'last_active' => '2026-07-29T11:00:00.000000Z', 'status' => 'active'],
            ],
        ],
    ], description: 'Standard Laravel paginator shape.')]
    public function adminList(AdminListRequest $request)
    {
        try {
            $listing = $request->validated();
            $export = $listing['export'] ?? null;

            return match ($export) {
                'csv' => $this->respondAdminCsv($listing),
                'pdf' => $this->respondAdminPdf($listing),
                default => JsonResponser::send(false, 'Admin users retrieved.', $this->paginatedPayload($this->adminUserService->list($listing), AdminListResource::class)),
            };
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\UserManagement\UserManagementController@adminList');
        }
    }

    #[Endpoint('List roles with permissions', 'Every role (unpaginated) with its full permission set expanded; useful for a permissions-matrix view. Requires the `roles.read` permission.')]
    #[Authenticated]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Roles with permissions retrieved.',
        'data' => [
            [
                'role_id' => 'b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e',
                'name' => 'Fundraising Officer',
                'description' => 'Manages campaigns, pledges, and donation monitoring.',
                'status' => 'active',
                'permissions' => ['campaigns.read', 'campaigns.create'],
                'permissions_by_module' => [['key' => 'user_management', 'label' => 'User Management', 'permissions' => [['name' => 'campaigns.read']]]],
                'updated_at' => '2026-07-29T09:00:00.000000Z',
            ],
        ],
    ], description: 'All roles (excluding the internal customer/donor guard) with permissions expanded.')]
    public function rolesWithPermissions()
    {
        try {
            $roles = $this->roleService->listWithPermissions();

            return JsonResponser::send(
                false,
                'Roles with permissions retrieved.',
                RoleResource::collection($roles)->resolve()
            );
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\UserManagement\UserManagementController@rolesWithPermissions');
        }
    }

    #[Endpoint('List all permissions', 'Every assignable permission, grouped by module; feeds the checkbox list on the create/edit role screen. Requires the `roles.read` permission.')]
    #[Authenticated]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Permissions retrieved.',
        'data' => [
            'permissions' => ['admins.read', 'admins.create', 'admins.update', 'admins.delete', 'roles.read', 'audit_trail.read'],
            'permissions_by_module' => [
                ['key' => 'user_management', 'label' => 'User Management', 'permissions' => [['name' => 'admins.read'], ['name' => 'admins.create']]],
                ['key' => 'audit_trail', 'label' => 'Audit Trail', 'permissions' => [['name' => 'audit_trail.read']]],
            ],
        ],
    ], description: 'Flat list plus the same permissions grouped by module.')]
    public function permissionList()
    {
        try {
            return JsonResponser::send(false, 'Permissions retrieved.', $this->roleService->listAllPermissions());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\UserManagement\UserManagementController@permissionList');
        }
    }

    #[Endpoint('Role dropdown', 'Lists roles for populating a role picker. The returned `role_id` values are what you pass to `POST /admin-users` when inviting an admin.')]
    #[Authenticated]
    #[UrlParam('status', 'string', 'Filter by role status.', required: false, example: 'active')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Role dropdown retrieved.',
        'data' => [
            ['role_id' => 'b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e', 'name' => 'Fundraising Officer', 'is_active' => true],
            ['role_id' => 'c3d4e5f6-a7b8-4c9d-0e1f-2a3b4c5d6e7f', 'name' => 'Super Admin', 'is_active' => true],
        ],
    ], description: 'Roles matching the requested status.')]
    public function roleDropdown(?string $status = null)
    {
        try {
            $roles = $this->roleService->dropdown($status ?? 'active');
            $payload = $roles->map(fn ($role) => [
                'role_id' => $role->uuid,
                'name' => $role->name,
                'is_active' => (bool) $role->is_active,
            ])->values()->all();

            return JsonResponser::send(false, 'Role dropdown retrieved.', $payload);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\UserManagement\UserManagementController@roleDropdown');
        }
    }

    #[Endpoint('Admin dropdown', 'Lists admins for a picker (e.g. assigning ownership elsewhere in the app). Requires the `admins.read` permission.')]
    #[Authenticated]
    #[UrlParam('status', 'string', 'Filter by admin status.', required: false, example: 'active')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Admin dropdown retrieved.',
        'data' => [
            ['admin_id' => 'a1b2c3d4-e5f6-4789-a0b1-c2d3e4f5a6b7', 'name' => 'Kelechi Ibrahim', 'email' => 'kibrahim@sbsc.com', 'is_active' => true],
        ],
    ], description: 'Admins matching the requested status.')]
    public function adminDropdown(?string $status = null)
    {
        try {
            $admins = $this->adminUserService->dropdown($status ?? 'active');
            $payload = $admins->map(fn ($admin) => [
                'admin_id' => $admin->uuid,
                'name' => $admin->name,
                'email' => $admin->email,
                'is_active' => (bool) $admin->is_active,
            ])->values()->all();

            return JsonResponser::send(false, 'Admin dropdown retrieved.', $payload);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\UserManagement\UserManagementController@adminDropdown');
        }
    }

    #[Endpoint('Invite a new admin', 'Creates the admin account and emails them a `Create Your Password` link (see Admin Auth / Forgot Password: `reset-password/context` and `reset-password`). Requires the `admins.create` permission; only a Super Admin may assign the Super Admin role.')]
    #[Authenticated]
    #[BodyParam('name', 'string', "The invitee's full name.", required: true, example: 'Kelechi Ibrahim')]
    #[BodyParam('email', 'string', 'Work email address; must be unique.', required: true, example: 'kibrahim@sbsc.com')]
    #[BodyParam('role_id', 'string', 'Role UUID from `GET /roles/dropdown`.', required: true, example: 'b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e')]
    #[BodyParam('is_active', 'boolean', 'Whether the account is active. Defaults to true.', required: false, example: true)]
    #[BodyParam('can_login', 'boolean', 'Whether the account may log in. Defaults to true.', required: false, example: true)]
    #[BodyParam('frontend_url', 'string', 'Override base URL the invite link points to; defaults to `ADMIN_FRONTEND_URL`.', required: false, example: 'https://admin.nhef.org')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Admin user created successfully.',
        'data' => [
            'admin_id' => 'a1b2c3d4-e5f6-4789-a0b1-c2d3e4f5a6b7',
            'name' => 'Kelechi Ibrahim',
            'email' => 'kibrahim@sbsc.com',
            'role_name' => 'Fundraising Officer',
            'permissions' => ['campaigns.read', 'campaigns.create'],
            'status' => 'active',
            'can_login' => true,
            'must_reset_password' => true,
            'last_active' => null,
        ],
    ], description: 'Admin created; invite email queued.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'An admin with this email already exists.',
        'data' => null,
    ], description: 'Validation error.')]
    #[Response(status: 403, content: [
        'error' => true,
        'message' => 'Only a Super Admin can assign the Super Admin role.',
        'data' => null,
    ], description: 'Attempted to assign the Super Admin role without being one.')]
    public function createAdmin(CreateAdminRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $this->assertCanAssignSuperAdminRole($request->validated('role_id'));
            $created = $this->adminUserService->create($request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Admin user created successfully.', AdminFullResource::make($created)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\UserManagement\UserManagementController@createAdmin');
        }
    }

    #[Endpoint('View an admin user', 'Loads the "View + Edit a User" screen. Requires the `admins.read` permission.')]
    #[Authenticated]
    #[UrlParam('adminId', 'string', "The admin's UUID.", required: true, example: 'a1b2c3d4-e5f6-4789-a0b1-c2d3e4f5a6b7')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Admin user retrieved.',
        'data' => [
            'admin_id' => 'a1b2c3d4-e5f6-4789-a0b1-c2d3e4f5a6b7',
            'name' => 'Adekunle Ibrahim Olamide',
            'email' => 'kibrahim@sbsc.com',
            'role_name' => 'Super Admin',
            'permissions' => ['admins.read', 'admins.create'],
            'permissions_by_module' => [['key' => 'user_management', 'label' => 'User Management', 'permissions' => [['name' => 'admins.read']]]],
            'status' => 'active',
            'can_login' => true,
            'must_reset_password' => false,
            'last_active' => '2026-07-29T11:00:00.000000Z',
        ],
    ], description: 'Full admin detail, including effective permissions from their role.')]
    public function viewAdmin(string $adminId)
    {
        try {
            $admin = $this->adminUserService->findAdmin($adminId);

            return JsonResponser::send(false, 'Admin user retrieved.', AdminFullResource::make($admin)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\UserManagement\UserManagementController@viewAdmin');
        }
    }

    #[Endpoint('Update an admin user', 'Backs the "Save Changes?" confirmation on the edit-user screen. Only send fields that changed. Requires the `admins.update` permission; only a Super Admin may assign the Super Admin role.')]
    #[Authenticated]
    #[UrlParam('adminId', 'string', "The admin's UUID.", required: true, example: 'a1b2c3d4-e5f6-4789-a0b1-c2d3e4f5a6b7')]
    #[BodyParam('name', 'string', 'Full name.', required: false, example: 'Adekunle Ibrahim Olamide')]
    #[BodyParam('email', 'string', 'Email address; must be unique.', required: false, example: 'kibrahim@sbsc.com')]
    #[BodyParam('role_id', 'string', 'New role UUID from `GET /roles/dropdown`.', required: false, example: 'b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e')]
    #[BodyParam('is_active', 'boolean', 'Active status.', required: false, example: true)]
    #[BodyParam('can_login', 'boolean', 'Whether the account may log in.', required: false, example: true)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Admin user updated.',
        'data' => [
            'admin_id' => 'a1b2c3d4-e5f6-4789-a0b1-c2d3e4f5a6b7',
            'name' => 'Adekunle Ibrahim Olamide',
            'email' => 'kibrahim@sbsc.com',
            'role_name' => 'Super Admin',
            'permissions' => ['admins.read', 'admins.create'],
            'status' => 'active',
            'can_login' => true,
            'must_reset_password' => false,
            'last_active' => '2026-07-29T11:00:00.000000Z',
        ],
    ], description: 'Updated admin detail.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'Another admin is already using this email.',
        'data' => null,
    ], description: 'Validation error.')]
    public function updateAdmin(UpdateAdminRequest $request, string $adminId)
    {
        try {
            $actor = $this->requireAdmin($request);
            $roleUuid = $request->validated('role_id');
            if (is_string($roleUuid) && $roleUuid !== '') {
                $this->assertCanAssignSuperAdminRole($roleUuid);
            }
            $admin = $this->adminUserService->update($adminId, $request->validated(), $actor, $request);

            return JsonResponser::send(false, 'Admin user updated.', AdminFullResource::make($admin)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\UserManagement\UserManagementController@updateAdmin');
        }
    }

    #[Endpoint('Toggle admin active status', "Flips activation: an active admin is deactivated, a deactivated one is reactivated (there's no separate activate/deactivate call, so read the returned `status` to see which happened). Backs both the Deactivate User confirmation and reactivating from the same button. Requires the `admins.update` permission; an admin can't toggle their own status.")]
    #[Authenticated]
    #[UrlParam('adminId', 'string', "The admin's UUID.", required: true, example: 'a1b2c3d4-e5f6-4789-a0b1-c2d3e4f5a6b7')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Admin user deactivated.',
        'data' => [
            'admin_id' => 'a1b2c3d4-e5f6-4789-a0b1-c2d3e4f5a6b7',
            'name' => 'Adekunle Ibrahim Olamide',
            'email' => 'kibrahim@sbsc.com',
            'role_name' => 'Super Admin',
            'status' => 'inactive',
            'can_login' => false,
        ],
    ], description: 'Status flipped; deactivating also sets `can_login` to false.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'You cannot toggle your own status as an admin.',
        'data' => null,
    ], description: 'An admin attempted to deactivate their own account.')]
    public function setAdminActiveStatus(Request $request, string $adminId)
    {
        try {
            $actor = $this->requireAdmin($request);
            $targetAdmin = $this->adminUserService->findAdmin($adminId);
            if ((string) $actor->id === (string) $targetAdmin->id) {
                return JsonResponser::send(true, 'You cannot toggle your own status as an admin.', null, 422);
            }

            $admin = $this->adminUserService->toggleActiveStatus($adminId, $actor, $request);
            $message = (bool) $admin->is_active ? 'Admin user activated.' : 'Admin user deactivated.';

            return JsonResponser::send(false, $message, AdminFullResource::make($admin)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\UserManagement\UserManagementController@setAdminActiveStatus');
        }
    }

    #[Endpoint('Resend invite / password-setup link', 'Mints a fresh reset token and re-sends the "Create Your Password" email. Only valid for admins still pending first-time password setup (i.e. `must_reset_password: true`); use this if the original invite link expired.')]
    #[Authenticated]
    #[UrlParam('adminId', 'string', "The admin's UUID.", required: true, example: 'a1b2c3d4-e5f6-4789-a0b1-c2d3e4f5a6b7')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Password setup link resent successfully.',
        'data' => [
            'admin_id' => 'a1b2c3d4-e5f6-4789-a0b1-c2d3e4f5a6b7',
            'name' => 'Kelechi Ibrahim',
            'email' => 'kibrahim@sbsc.com',
            'must_reset_password' => true,
        ],
    ], description: 'New invite link queued for delivery.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'Reset link can only be resent for admins pending first-time password setup.',
        'data' => null,
    ], description: 'Admin has already set their password.')]
    public function resendAdminInviteLink(Request $request, string $adminId)
    {
        try {
            $actor = $this->requireAdmin($request);
            $admin = $this->adminUserService->resendInviteResetLink($adminId, $actor, $request);

            return JsonResponser::send(false, 'Password setup link resent successfully.', AdminFullResource::make($admin)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\UserManagement\UserManagementController@resendAdminInviteLink');
        }
    }

    #[Endpoint('Delete an admin user', 'Backs the "Delete User?" confirmation. Permanent; blocked if the admin has audit log history (see 422 below), since audit logs are immutable and must keep pointing at a real record. Requires the `admins.delete` permission; an admin cannot delete their own account.')]
    #[Authenticated]
    #[UrlParam('adminId', 'string', "The admin's UUID.", required: true, example: 'a1b2c3d4-e5f6-4789-a0b1-c2d3e4f5a6b7')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Admin user deleted successfully.',
        'data' => null,
    ], description: 'Admin permanently deleted.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'Admin cannot be deleted because of audit logs tied to them.',
        'data' => ['audit_logs_count' => 12],
    ], description: 'Admin has audit log history; deactivate them instead of deleting.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'You cannot delete your own admin account.',
        'data' => null,
    ], description: 'An admin attempted to delete their own account.')]
    public function deleteAdmin(Request $request, string $adminId)
    {
        try {
            $actor = $this->requireAdmin($request);
            $targetAdmin = $this->adminUserService->findAdmin($adminId);
            if ((string) $actor->id === (string) $targetAdmin->id) {
                return JsonResponser::send(true, 'You cannot delete your own admin account.', null, 422);
            }

            $result = $this->adminUserService->delete($adminId, $actor, $request);
            $auditLogsCount = $result['audit_logs_count'];
            if ($auditLogsCount > 0) {
                return JsonResponser::send(true, 'Admin cannot be deleted because of audit logs tied to them.', [
                    'audit_logs_count' => $auditLogsCount,
                ], 422);
            }

            return JsonResponser::send(false, 'Admin user deleted successfully.', null);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\UserManagement\UserManagementController@deleteAdmin');
        }
    }

    #[Endpoint('View a role', 'Requires the `roles.read` permission.')]
    #[Authenticated]
    #[UrlParam('roleId', 'string', "The role's UUID.", required: true, example: 'b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Role retrieved.',
        'data' => [
            'role_id' => 'b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e',
            'name' => 'Fundraising Officer',
            'description' => 'Manages campaigns, pledges, and donation monitoring.',
            'status' => 'active',
            'permissions' => ['campaigns.read', 'campaigns.create'],
            'permissions_by_module' => [['key' => 'user_management', 'label' => 'User Management', 'permissions' => [['name' => 'campaigns.read']]]],
            'updated_at' => '2026-07-29T09:00:00.000000Z',
        ],
    ], description: 'Full role detail with permissions expanded.')]
    public function viewRole(string $roleId)
    {
        try {
            $role = $this->roleService->findRole($roleId);

            return JsonResponser::send(false, 'Role retrieved.', RoleResource::make($role)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\UserManagement\UserManagementController@viewRole');
        }
    }

    #[Endpoint('Update a role', 'Only send fields that changed; `permissions` (if sent) fully replaces the current set rather than merging. Requires the `roles.update` permission. Deactivating is blocked while any admin still holds the role.')]
    #[Authenticated]
    #[UrlParam('roleId', 'string', "The role's UUID.", required: true, example: 'b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e')]
    #[BodyParam('name', 'string', 'Role name; must be unique.', required: false, example: 'Fundraising Officer')]
    #[BodyParam('description', 'string', 'What the role is for.', required: false, example: 'Manages campaigns, pledges, and donation monitoring.')]
    #[BodyParam('is_active', 'boolean', 'Whether the role is assignable.', required: false, example: true)]
    #[BodyParam('permissions', 'string[]', 'Full replacement list of permission names.', required: false, example: ['campaigns.read', 'campaigns.create'])]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Role updated.',
        'data' => [
            'role_id' => 'b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e',
            'name' => 'Fundraising Officer',
            'description' => 'Manages campaigns, pledges, and donation monitoring.',
            'status' => 'active',
            'permissions' => ['campaigns.read', 'campaigns.create'],
            'updated_at' => '2026-07-29T09:10:00.000000Z',
        ],
    ], description: 'Updated role detail.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'Role cannot be deactivated because it is assigned to one or more admin users.',
        'data' => null,
    ], description: 'Attempted to deactivate a role that still has admins assigned.')]
    public function updateRole(UpdateRoleRequest $request, string $roleId)
    {
        try {
            $admin = $this->requireAdmin($request);
            $role = $this->roleService->update($roleId, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Role updated.', RoleResource::make($role)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\UserManagement\UserManagementController@updateRole');
        }
    }

    #[Endpoint('Toggle role active status', "Flips activation: an active role is deactivated, an inactive one is reactivated (there's no separate activate/deactivate call). Requires the `roles.update` permission. Deactivating is blocked while any admin still holds the role.")]
    #[Authenticated]
    #[UrlParam('roleId', 'string', "The role's UUID.", required: true, example: 'b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Role deactivated.',
        'data' => [
            'role_id' => 'b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e',
            'name' => 'Fundraising Officer',
            'status' => 'inactive',
        ],
    ], description: 'Status flipped.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'Role cannot be deactivated because it is assigned to one or more admin users.',
        'data' => null,
    ], description: 'Role still has admins assigned.')]
    public function setRoleActiveStatus(Request $request, string $roleId)
    {
        try {
            $admin = $this->requireAdmin($request);
            $role = $this->roleService->toggleActiveStatus($roleId, $admin, $request);
            $message = (bool) $role->is_active ? 'Role activated.' : 'Role deactivated.';

            return JsonResponser::send(false, $message, RoleResource::make($role)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\UserManagement\UserManagementController@setRoleActiveStatus');
        }
    }

    #[Endpoint('Delete a role', 'Permanent; blocked while any admin still holds the role (reassign them first). Requires the `roles.delete` permission.')]
    #[Authenticated]
    #[UrlParam('roleId', 'string', "The role's UUID.", required: true, example: 'b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Role deleted successfully.',
        'data' => null,
    ], description: 'Role permanently deleted.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'Role cannot be deleted because it is assigned to one or more admin users.',
        'data' => ['admin_users_count' => 3],
    ], description: 'Role still has admins assigned; reassign them first.')]
    public function deleteRole(Request $request, string $roleId)
    {
        try {
            $admin = $this->requireAdmin($request);
            $result = $this->roleService->delete($roleId, $admin, $request);
            $adminUsersCount = $result['admin_users_count'];
            if ($adminUsersCount > 0) {
                return JsonResponser::send(true, 'Role cannot be deleted because it is assigned to one or more admin users.', [
                    'admin_users_count' => $adminUsersCount,
                ], 422);
            }

            return JsonResponser::send(false, 'Role deleted successfully.', null);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\UserManagement\UserManagementController@deleteRole');
        }
    }

    /**
     * @param  array<string, mixed>  $listing
     */
    private function respondRoleCsv(array $listing): StreamedResponse
    {
        [$collection, $truncated] = $this->roleService->exportCollection($listing);
        $filename = 'roles-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($collection): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'ID',
                'Name',
                'Number of users',
                'Status',
                'Last updated',
            ]);

            $rowNumber = 0;
            foreach ($collection as $role) {
                /** @var Role $role */
                $rowNumber++;
                fputcsv($out, [
                    $rowNumber,
                    $role->name,
                    (int) ($role->users_count ?? 0),
                    $role->is_active ? 'active' : 'inactive',
                    $role->updated_at?->toIso8601String() ?? '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Export-Truncated' => $truncated ? '1' : '0',
        ]);
    }

    /**
     * @param  array<string, mixed>  $listing
     */
    private function respondRolePdf(array $listing)
    {
        [$collection, $truncated] = $this->roleService->exportCollection($listing);
        $filename = 'roles-'.now()->format('Y-m-d-His').'.pdf';
        $periodStart = ! empty($listing['start_date']) ? (string) $listing['start_date'] : 'All dates';
        $periodEnd = ! empty($listing['end_date']) ? (string) $listing['end_date'] : 'All dates';

        $headings = [
            'ID',
            'Name',
            'Number of users',
            'Status',
            'Last updated',
        ];

        $rows = $collection->values()->map(fn (Role $role, int $index): array => [
            $index + 1,
            $role->name,
            (string) (int) ($role->users_count ?? 0),
            $role->is_active ? 'active' : 'inactive',
            $role->updated_at?->format('Y-m-d H:i') ?? '',
        ]);

        return $this->pdfReportHelper->download(
            rows: $rows,
            headings: $headings,
            title: 'Roles',
            filename: $filename,
            orientation: 'landscape',
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            generatedAt: now((string) config('app.timezone')),
            truncated: $truncated,
            includedRows: $rows->count(),
        );
    }

    /**
     * @param  array<string, mixed>  $listing
     */
    private function respondAdminCsv(array $listing): StreamedResponse
    {
        [$collection, $truncated] = $this->adminUserService->exportCollection($listing);
        $filename = 'admin-users-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($collection): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'ID',
                'Name',
                'Role',
                'Email',
                'Last active',
                'Status',
            ]);

            $rowNumber = 0;
            foreach ($collection as $admin) {
                /** @var Admin $admin */
                $rowNumber++;
                fputcsv($out, [
                    $rowNumber,
                    $admin->name,
                    $admin->roles->first()?->name ?? '',
                    $admin->email,
                    $admin->last_active_at?->toIso8601String() ?? '',
                    $admin->is_active ? 'active' : 'inactive',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Export-Truncated' => $truncated ? '1' : '0',
        ]);
    }

    /**
     * @param  array<string, mixed>  $listing
     */
    private function respondAdminPdf(array $listing)
    {
        [$collection, $truncated] = $this->adminUserService->exportCollection($listing);
        $filename = 'admin-users-'.now()->format('Y-m-d-His').'.pdf';
        $periodStart = ! empty($listing['start_date']) ? (string) $listing['start_date'] : 'All dates';
        $periodEnd = ! empty($listing['end_date']) ? (string) $listing['end_date'] : 'All dates';

        $headings = [
            'ID',
            'Name',
            'Role',
            'Email',
            'Last active',
            'Status',
        ];

        $rows = $collection->values()->map(fn (Admin $admin, int $index): array => [
            $index + 1,
            $admin->name,
            $admin->roles->first()?->name ?? '',
            $admin->email,
            $admin->last_active_at?->format('Y-m-d H:i') ?? '',
            $admin->is_active ? 'active' : 'inactive',
        ]);

        return $this->pdfReportHelper->download(
            rows: $rows,
            headings: $headings,
            title: 'Admin users',
            filename: $filename,
            orientation: 'landscape',
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            generatedAt: now((string) config('app.timezone')),
            truncated: $truncated,
            includedRows: $rows->count(),
        );
    }

    /**
     * @param  class-string<\Illuminate\Http\Resources\Json\JsonResource>  $resourceClass
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator, string $resourceClass): array
    {
        $payload = $paginator->toArray();
        /** @var \Illuminate\Http\Resources\Json\AnonymousResourceCollection $resource */
        $resource = $resourceClass::collection($paginator);
        $payload['data'] = $resource->resolve();

        return $payload;
    }

    private function assertCanAssignSuperAdminRole(string $roleUuid): void
    {
        $role = Role::query()
            ->where('guard_name', 'api')
            ->where('uuid', $roleUuid)
            ->first();

        if ($role === null || $role->name !== eRole::SUPER_ADMIN->value) {
            return;
        }

        $authAdmin = request()->user();
        if (! ($authAdmin instanceof Admin) || ! $authAdmin->hasRole(eRole::SUPER_ADMIN->value)) {
            abort(403, 'Only a Super Admin can assign the Super Admin role.');
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
