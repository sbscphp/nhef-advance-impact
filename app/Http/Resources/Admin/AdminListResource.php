<?php

namespace App\Http\Resources\Admin;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin row for user-management listing tables.
 *
 * @mixin Admin
 */
class AdminListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['roles:id,name', 'institution:id,uuid,name']);

        return [
            'admin_id' => $this->uuid,
            'user_code' => $this->code(),
            'name' => $this->name,
            'job_title' => $this->job_title,
            'role' => $this->roles->first()?->name,
            'email' => $this->email,
            // Null for NHEF-level admins (Super Admin, Admin); set for an Institution Admin.
            'institution' => $this->institution === null ? null : [
                'uuid' => $this->institution->uuid,
                'name' => $this->institution->name,
            ],
            'last_active' => $this->last_active_at,
            'status' => $this->is_active ? 'active' : 'inactive',
        ];
    }
}
