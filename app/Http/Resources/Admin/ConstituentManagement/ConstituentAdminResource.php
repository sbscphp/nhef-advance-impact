<?php

namespace App\Http\Resources\Admin\ConstituentManagement;

use App\Enums\ConstituentTypeEnum;
use App\Http\Resources\TertiaryInstitutionResource;
use App\Models\User;
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
            'constituent_type' => $this->constituent_type,
            'constituent_type_label' => ConstituentTypeEnum::tryFrom((string) $this->constituent_type)?->label(),
            'avatar_url' => $this->profile_picture_url,
            'university' => $this->whenLoaded('tertiaryInstitution', fn () => $this->tertiaryInstitution === null ? null : TertiaryInstitutionResource::make($this->tertiaryInstitution)),
            'department' => $this->department,
            'year_of_graduation' => $this->year_of_graduation,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'date_added' => $this->created_at?->toIso8601String(),
            'date_onboarded' => $this->onboarded_at?->toIso8601String(),
        ];
    }
}
