<?php

namespace App\Http\Resources\Communications;

use App\Models\CommunicationCallLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CommunicationCallLog */
class CommunicationCallLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'log_type' => $this->log_type,
            'summary' => $this->description,
            'priority' => $this->priority,
            'duration_minutes' => $this->duration_minutes,
            'next_action' => $this->next_action,
            'other_participants' => $this->other_participants,
            'phone_number' => $this->phone_number,
            'email_subject' => $this->email_subject,
            'email_address' => $this->email_address,
            'location' => $this->location,
            'call_date' => $this->call_date?->toIso8601String(),
            'contact' => $this->whenLoaded('contact', fn () => $this->contact === null ? null : [
                'uuid' => $this->contact->uuid,
                'name' => $this->contact->displayName(),
                'email' => $this->contact->email,
            ]),
            'logged_by' => $this->whenLoaded('logger', fn () => $this->logger === null ? null : [
                'uuid' => $this->logger->uuid,
                'name' => $this->logger->displayName(),
            ]),
            'follow_up_tasks' => CommunicationTaskResource::collection($this->whenLoaded('followUpTasks')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
