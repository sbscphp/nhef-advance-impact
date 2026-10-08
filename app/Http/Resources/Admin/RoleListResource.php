<?php

namespace App\Http\Resources\Admin;

use App\Enums\eRole;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'role_id' => $this->uuid,
            'role_code' => $this->code(),
            'name' => $this->name,
            'description' => $this->description,
            'is_system_role' => in_array($this->name, eRole::values(), true),
            'number_of_users' => (int) ($this->users_count ?? 0),
            'status' => $this->is_active ? 'active' : 'inactive',
            'last_updated' => $this->updated_at,
        ];
    }
}
