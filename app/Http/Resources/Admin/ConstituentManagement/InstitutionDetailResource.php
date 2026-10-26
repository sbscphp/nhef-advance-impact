<?php

namespace App\Http\Resources\Admin\ConstituentManagement;

use App\Models\Institution;
use App\Support\ViewerVisibility;
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
            'slug' => $this->slug,
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
            // Every contact - the three required offices (role: vc/advancement_director/
            // advancement_database_manager, see InstitutionRequiredOfficerRoleEnum) plus any
            // further custom one - lives here now (2026-10-26, FE feedback), managed via
            // POST/PATCH/DELETE .../officers. The Advancement Database Manager entry's own
            // email/name become the institution's admin login (see AdminInstitutionService::invite()).
            'officers' => InstitutionOfficerResource::collection($this->whenLoaded('officers')),
            'status' => $this->status,
            'is_active' => $this->is_active,
            'date_added' => $this->created_at?->toIso8601String(),
            'date_onboarded' => $this->onboarded_at?->toIso8601String(),
            'invited_at' => $this->invited_at?->toIso8601String(),
            ...$this->statsPayload(),
            ...ViewerVisibility::money($this->moneyStatsPayload()),
        ];
    }
}
