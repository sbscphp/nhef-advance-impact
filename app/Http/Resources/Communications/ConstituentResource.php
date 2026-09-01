<?php

namespace App\Http\Resources\Communications;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class ConstituentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->displayName(),
            'email' => $this->email,
            'university' => $this->university,
            'department' => $this->department,
            'year_of_graduation' => $this->year_of_graduation,
        ];
    }
}
