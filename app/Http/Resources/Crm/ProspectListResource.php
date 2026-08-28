<?php

namespace App\Http\Resources\Crm;

use App\Models\Prospect;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Lighter payload for the Kanban board cards. @mixin Prospect */
class ProspectListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->fullName(),
            'currency' => $this->currency,
            'estimated_value' => (string) $this->estimated_value,
            'estimated_value_formatted' => Money::format($this->estimated_value, $this->currency),
            'stage' => $this->stage,
            'assigned_admin' => $this->whenLoaded('assignedAdmin', fn () => $this->assignedAdmin === null ? null : [
                'uuid' => $this->assignedAdmin->uuid,
                'name' => $this->assignedAdmin->displayName(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
