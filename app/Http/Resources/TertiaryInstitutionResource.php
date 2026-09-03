<?php

namespace App\Http\Resources;

use App\Models\TertiaryInstitution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TertiaryInstitution */
class TertiaryInstitutionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'type' => $this->type,
            'state' => $this->state,
            'city' => $this->city,
            'abbreviation' => $this->abbreviation,
        ];
    }
}
