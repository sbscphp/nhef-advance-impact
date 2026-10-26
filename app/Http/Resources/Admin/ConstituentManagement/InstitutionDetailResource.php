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
            // Client requirement (2026-10-23): captured on invite for NHEF's own records. The
            // Advancement Database Manager's own email/name become the institution's admin login
            // (see AdminInstitutionService::invite()); the other two are contact info only.
            'personnel' => [
                'vc' => ['name' => $this->vc_name, 'email' => $this->vc_email, 'phone_number' => $this->vc_phone_number],
                'advancement_director' => ['name' => $this->advancement_director_name, 'email' => $this->advancement_director_email, 'phone_number' => $this->advancement_director_phone_number],
                'advancement_database_manager' => ['name' => $this->advancement_database_manager_name, 'email' => $this->advancement_database_manager_email, 'phone_number' => $this->advancement_database_manager_phone_number],
            ],
            // Any further custom officers beyond the three fixed ones above (2026-10-26) -
            // free-typed `role` per entry, managed via POST/PATCH/DELETE .../officers.
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
