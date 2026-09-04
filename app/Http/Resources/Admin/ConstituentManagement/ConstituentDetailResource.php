<?php

namespace App\Http\Resources\Admin\ConstituentManagement;

use App\Http\Resources\TertiaryInstitutionResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class ConstituentDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'code' => $this->code(),
            'first_name' => $this->firstname,
            'last_name' => $this->lastname,
            'name' => $this->displayName(),
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'avatar_url' => $this->profile_picture_url,
            'university' => $this->whenLoaded('tertiaryInstitution', fn () => $this->tertiaryInstitution === null ? null : TertiaryInstitutionResource::make($this->tertiaryInstitution)),
            'department' => $this->department,
            'year_of_graduation' => $this->year_of_graduation,
            'tier' => $this->tier,
            'invite_message' => $this->invite_message,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'date_joined' => $this->created_at?->toIso8601String(),
            'date_onboarded' => $this->onboarded_at?->toIso8601String(),
            'invited_at' => $this->invited_at?->toIso8601String(),
        ];
    }
}
