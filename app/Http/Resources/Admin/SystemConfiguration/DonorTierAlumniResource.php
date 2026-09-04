<?php

namespace App\Http\Resources\Admin\SystemConfiguration;

use App\Http\Resources\TertiaryInstitutionResource;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class DonorTierAlumniResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->displayName(),
            'institution' => $this->whenLoaded('tertiaryInstitution', fn () => $this->tertiaryInstitution === null ? null : TertiaryInstitutionResource::make($this->tertiaryInstitution)),
            'email' => $this->email,
            'donations_count' => (int) $this->payments_count,
            'lifetime_total' => (string) $this->lifetime_total,
            'lifetime_total_formatted' => Money::format($this->lifetime_total, 'NGN'),
            'upgraded_at' => $this->upgraded_at?->toIso8601String(),
        ];
    }
}
