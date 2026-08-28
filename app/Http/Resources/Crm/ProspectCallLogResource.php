<?php

namespace App\Http\Resources\Crm;

use App\Models\ProspectCallLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProspectCallLog */
class ProspectCallLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'purpose' => $this->purpose,
            'description' => $this->description,
            'priority' => $this->priority,
            'call_date' => $this->call_date?->toIso8601String(),
            'logged_by' => $this->whenLoaded('logger', fn () => $this->logger === null ? null : [
                'uuid' => $this->logger->uuid,
                'name' => $this->logger->displayName(),
            ]),
            // Same admin as `logged_by`; there is no separate contact-person input.
            'contact_person' => $this->whenLoaded('logger', fn () => $this->logger === null ? null : [
                'uuid' => $this->logger->uuid,
                'name' => $this->logger->displayName(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
