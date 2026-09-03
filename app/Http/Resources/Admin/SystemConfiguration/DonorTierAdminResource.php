<?php

namespace App\Http\Resources\Admin\SystemConfiguration;

use App\Models\DonorTier;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DonorTier */
class DonorTierAdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
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
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
