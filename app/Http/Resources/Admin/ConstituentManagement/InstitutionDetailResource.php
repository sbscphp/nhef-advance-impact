<?php

namespace App\Http\Resources\Admin\ConstituentManagement;

use App\Models\Institution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Institution */
class InstitutionDetailResource extends JsonResource
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
            'tertiary_institution' => $this->whenLoaded('tertiaryInstitution', fn () => $this->tertiaryInstitution === null ? null : [
                'uuid' => $this->tertiaryInstitution->uuid,
                'name' => $this->tertiaryInstitution->name,
            ]),
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'country' => $this->country,
            'state' => $this->state,
            'address' => $this->address,
            'invite_message' => $this->invite_message,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'date_added' => $this->created_at?->toIso8601String(),
            'date_onboarded' => $this->onboarded_at?->toIso8601String(),
            'invited_at' => $this->invited_at?->toIso8601String(),
        ];
    }
}
