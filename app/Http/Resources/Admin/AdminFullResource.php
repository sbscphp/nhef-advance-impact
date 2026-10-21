<?php

namespace App\Http\Resources\Admin;

use App\Helpers\PermissionModuleMapper;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full admin detail for user-management views (role + effective permissions).
 *
 * @mixin Admin
 */
class AdminFullResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['roles.permissions', 'institution:id,uuid,name']);
        $role = $this->roles->first();
        $permissionNames = $role?->permissions->pluck('name')->values()->all() ?? [];

        return [
            'admin_id' => $this->uuid,
            'user_code' => $this->code(),
            'name' => $this->name,
            'job_title' => $this->job_title,
            'email' => $this->email,
            // Null for NHEF-level admins (Super Admin, Admin); set for an Institution Admin.
            'institution' => $this->institution === null ? null : [
                'uuid' => $this->institution->uuid,
                'name' => $this->institution->name,
            ],
            'role_id' => $role?->uuid,
            'role_name' => $role?->name,
            'permissions' => $permissionNames,
            'permissions_by_module' => PermissionModuleMapper::groupedApiPermissionsForNames($permissionNames),
            'status' => $this->is_active ? 'active' : 'inactive',
            'can_login' => (bool) $this->can_login,
            'must_reset_password' => (bool) $this->must_reset_password,
            'last_active' => $this->last_active_at,
        ];
    }
}
