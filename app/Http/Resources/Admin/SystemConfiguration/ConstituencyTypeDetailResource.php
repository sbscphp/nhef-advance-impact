<?php

namespace App\Http\Resources\Admin\SystemConfiguration;

use App\Models\Admin;
use App\Models\ConstituencyType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ConstituencyType */
class ConstituencyTypeDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $creator = $this->whenLoaded('creator');

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'status' => $this->is_active ? 'active' : 'inactive',
            'is_active' => $this->is_active,
            'created_by' => $creator instanceof Admin ? $this->formatCreator($creator) : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'usage_count' => $this->usage_count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCreator(Admin $creator): array
    {
        $role = $creator->roles->first()?->name;

        return [
            'admin_id' => $creator->id,
            'admin_uuid' => $creator->uuid,
            'name' => $creator->displayName(),
            'role' => $role,
            'label' => $creator->displayName().($role !== null ? " ({$role})" : '')." ; ID:{$creator->id}",
        ];
    }
}
