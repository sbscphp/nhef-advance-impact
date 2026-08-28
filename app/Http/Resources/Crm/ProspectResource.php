<?php

namespace App\Http\Resources\Crm;

use App\Models\Prospect;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Prospect */
class ProspectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $this->fullName(),
            'email' => $this->email,
            'phone' => $this->phone,
            'lead_source' => $this->lead_source,
            'currency' => $this->currency,
            'estimated_value' => (string) $this->estimated_value,
            'estimated_value_formatted' => Money::format($this->estimated_value, $this->currency),
            'stage' => $this->stage,
            'stage_entered_at' => $this->stage_entered_at?->toIso8601String(),
            'days_in_stage' => $this->daysInCurrentStage(),
            'pipeline' => $this->pipelineSteps(),
            'assigned_admin' => $this->whenLoaded('assignedAdmin', fn () => $this->assignedAdmin === null ? null : [
                'uuid' => $this->assignedAdmin->uuid,
                'name' => $this->assignedAdmin->displayName(),
            ]),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator === null ? null : [
                'uuid' => $this->creator->uuid,
                'name' => $this->creator->displayName(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
