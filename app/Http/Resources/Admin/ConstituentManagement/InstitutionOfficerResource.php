<?php

namespace App\Http\Resources\Admin\ConstituentManagement;

use App\Models\InstitutionOfficer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InstitutionOfficer */
class InstitutionOfficerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'role' => $this->role,
            'name' => $this->name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
