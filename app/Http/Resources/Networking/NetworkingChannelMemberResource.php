<?php

namespace App\Http\Resources\Networking;

use App\Http\Resources\TertiaryInstitutionResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class NetworkingChannelMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'first_name' => $this->firstname,
            'last_name' => $this->lastname,
            'email' => $this->email,
            'phone' => $this->phone_number,
            'department' => $this->department,
            'university' => $this->whenLoaded('tertiaryInstitution', fn () => $this->tertiaryInstitution === null ? null : TertiaryInstitutionResource::make($this->tertiaryInstitution)),
            'year_of_graduation' => $this->year_of_graduation,
            'organisation_name' => $this->organisation_name,
            'avatar_url' => $this->profile_picture_url,
        ];
    }
}
