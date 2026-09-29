<?php

namespace App\Http\Resources\ConstituentManagement;

use App\Http\Resources\TertiaryInstitutionResource;
use App\Models\Institution;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Institution */
class FeaturedInstitutionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'logo_url' => $this->logoUrl(),
            'tertiary_institution' => $this->whenLoaded('tertiaryInstitution', fn () => $this->tertiaryInstitution === null ? null : TertiaryInstitutionResource::make($this->tertiaryInstitution)),
            'active_campaigns_count' => (int) $this->active_campaigns_count,
            'active_events_count' => (int) $this->active_events_count,
            'total_donations' => (string) $this->total_donations,
            'total_donations_formatted' => Money::format($this->total_donations, 'NGN'),
            'donors_count' => (int) $this->donors_count,
            'alumni_count' => (int) $this->alumni_count,
            'non_alumni_count' => (int) $this->non_alumni_count,
            'organisation_count' => (int) $this->organisation_count,
        ];
    }
}
