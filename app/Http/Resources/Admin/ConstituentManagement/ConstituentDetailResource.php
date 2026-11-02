<?php

namespace App\Http\Resources\Admin\ConstituentManagement;

use App\Enums\ConstituentTypeEnum;
use App\Http\Resources\Admin\SystemConfiguration\ConstituencyTypeAdminResource;
use App\Http\Resources\TertiaryInstitutionResource;
use App\Models\User;
use App\Support\Money;
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
            'middle_name' => $this->middlename,
            'name' => $this->displayName(),
            'email' => $this->email,
            'constituent_type' => $this->constituent_type,
            'constituent_type_label' => ConstituentTypeEnum::tryFrom((string) $this->constituent_type)?->label(),
            'phone_number' => $this->phone_number,
            'avatar_url' => $this->profile_picture_url,
            'university' => $this->whenLoaded('tertiaryInstitution', fn () => $this->tertiaryInstitution === null ? null : TertiaryInstitutionResource::make($this->tertiaryInstitution)),
            'department' => $this->department,
            'year_of_graduation' => $this->year_of_graduation,
            'degree_earned' => $this->degree_earned,
            'organisation_name' => $this->organisation_name,
            'position' => $this->position,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'country_of_residence' => $this->whenLoaded('countryOfResidence', fn () => $this->countryOfResidence === null ? null : [
                'uuid' => $this->countryOfResidence->uuid,
                'name' => $this->countryOfResidence->name,
            ]),
            'sector_of_employment' => $this->sector_of_employment,
            'area_of_interest' => $this->area_of_interest,
            'address' => $this->address,
            'organisation_type' => $this->organisation_type,
            'organisation_description' => $this->organisation_description,
            'rc_number' => $this->rc_number,
            'date_of_incorporation' => $this->date_of_incorporation?->toDateString(),
            'website' => $this->website,
            'country_of_operation' => $this->whenLoaded('countryOfOperation', fn () => $this->countryOfOperation === null ? null : [
                'uuid' => $this->countryOfOperation->uuid,
                'name' => $this->countryOfOperation->name,
            ]),
            'organisation_size' => $this->organisation_size,
            'sector_of_operation' => $this->sector_of_operation,
            'engagement_preference' => $this->engagement_preference,
            'tier' => $this->tier,
            'tier_color' => $this->tier_color,
            'constituency_types' => $this->whenLoaded('constituencyTypes', fn () => ConstituencyTypeAdminResource::collection($this->constituencyTypes)->resolve()),
            'invite_message' => $this->invite_message,
            // Present only when the service has attached them (showForAdmin);
            // avoids a false "0" on call sites that build this resource without that batch query.
            'donations_count' => $this->when(array_key_exists('donations_count', $this->resource->getAttributes()), fn () => (int) $this->donations_count),
            'total_donations' => $this->when(array_key_exists('total_donations', $this->resource->getAttributes()), fn () => (string) $this->total_donations),
            'total_donations_formatted' => $this->when(array_key_exists('total_donations', $this->resource->getAttributes()), fn () => Money::format($this->total_donations, 'NGN')),
            'status' => $this->status,
            'is_active' => $this->is_active,
            'last_active_at' => $this->last_active_at?->toIso8601String(),
            'date_joined' => $this->created_at?->toIso8601String(),
            'date_onboarded' => $this->onboarded_at?->toIso8601String(),
            'invited_at' => $this->invited_at?->toIso8601String(),
        ];
    }
}
