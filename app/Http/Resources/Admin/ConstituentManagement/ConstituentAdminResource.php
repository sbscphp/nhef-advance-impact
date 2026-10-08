<?php

namespace App\Http\Resources\Admin\ConstituentManagement;

use App\Enums\ConstituentTypeEnum;
use App\Http\Resources\TertiaryInstitutionResource;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class ConstituentAdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'code' => $this->code(),
            'name' => $this->displayName(),
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'constituent_type' => $this->constituent_type,
            'constituent_type_label' => ConstituentTypeEnum::tryFrom((string) $this->constituent_type)?->label(),
            'avatar_url' => $this->profile_picture_url,
            'university' => $this->whenLoaded('tertiaryInstitution', fn () => $this->tertiaryInstitution === null ? null : TertiaryInstitutionResource::make($this->tertiaryInstitution)),
            'department' => $this->department,
            'year_of_graduation' => $this->year_of_graduation,
            // Present only when the service has attached them (paginateForAdmin/exportForAdmin);
            // avoids a false "0" on call sites that build this resource without that batch query.
            'donations_count' => $this->when(array_key_exists('donations_count', $this->resource->getAttributes()), fn () => (int) $this->donations_count),
            'total_donations' => $this->when(array_key_exists('total_donations', $this->resource->getAttributes()), fn () => (string) $this->total_donations),
            'total_donations_formatted' => $this->when(array_key_exists('total_donations', $this->resource->getAttributes()), fn () => Money::format($this->total_donations, 'NGN')),
            'status' => $this->status,
            'is_active' => $this->is_active,
            'last_active_at' => $this->last_active_at?->toIso8601String(),
            'date_added' => $this->created_at?->toIso8601String(),
            'date_onboarded' => $this->onboarded_at?->toIso8601String(),
        ];
    }
}
