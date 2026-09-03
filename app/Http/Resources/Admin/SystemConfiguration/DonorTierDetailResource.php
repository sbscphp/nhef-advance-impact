<?php

namespace App\Http\Resources\Admin\SystemConfiguration;

use App\Models\Admin;
use App\Models\DonorTier;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DonorTier */
class DonorTierDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $creator = $this->whenLoaded('creator');

        return [
            'uuid' => $this->uuid,
            'code' => $this->code(),
            'name' => $this->name,
            'minimum_amount' => (string) $this->minimum_amount,
            'minimum_amount_formatted' => Money::format($this->minimum_amount, 'NGN'),
            'maximum_amount' => $this->maximum_amount !== null ? (string) $this->maximum_amount : null,
            'maximum_amount_formatted' => $this->maximum_amount !== null ? Money::format($this->maximum_amount, 'NGN') : null,
            'badge_url' => $this->badge_url,
            'status' => $this->is_active ? 'active' : 'inactive',
            'is_active' => $this->is_active,
            'created_by' => $creator instanceof Admin ? $this->formatCreator($creator) : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'alumni_count' => $this->alumni_count,
            'institution_count' => $this->institution_count,
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
