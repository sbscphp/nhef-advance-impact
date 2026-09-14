<?php

namespace App\Http\Resources\Admin\ConstituentManagement;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects `donation_count` already set on the model (see AdminInstitutionService::alumni()).
 *
 * @mixin User
 */
class InstitutionAlumniResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'id' => 'NHEF-AD-'.strtoupper(substr($this->uuid, 0, 6)),
            'name' => trim($this->firstname.' '.$this->lastname),
            'email' => $this->email,
            'no_of_donations' => (int) ($this->donation_count ?? 0),
            'department' => $this->department,
            'status' => $this->is_active ? 'active' : 'inactive',
            'last_active' => $this->last_active_at?->toIso8601String(),
        ];
    }
}
