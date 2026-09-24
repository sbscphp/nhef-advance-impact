<?php

namespace App\Http\Resources\Admin;

use App\Enums\eRole;
use App\Helpers\PermissionModuleMapper;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('permissions:id,name');
        $permissionNames = $this->permissions->pluck('name')->values()->all();

        return [
            'role_id' => $this->uuid,
            'role_code' => $this->code(),
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->is_active ? 'active' : 'inactive',
            'is_system_role' => in_array($this->name, eRole::values(), true),
            'number_of_users' => $this->users_count ?? $this->admins()->count(),
            'permissions' => $permissionNames,
            'permissions_by_module' => PermissionModuleMapper::groupedApiPermissionsForNames($permissionNames),
            'permission_matrix' => PermissionModuleMapper::matrix($permissionNames),
            'updated_at' => $this->updated_at,
        ];
    }
}
