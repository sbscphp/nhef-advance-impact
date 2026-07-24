<?php

namespace App\Http\Resources\Events;

use App\Models\EventRegistration;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EventRegistration */
class EventRegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'event' => EventResource::make($this->whenLoaded('event')),
            'amount' => (string) $this->amount,
            'amount_formatted' => Money::format($this->amount, $this->currency),
            'currency' => $this->currency,
            'status' => $this->status,
            'is_guest' => $this->isGuest(),
            'attendee_name' => $this->attendeeName(),
            'attendee_email' => $this->attendeeEmail(),
            'items' => EventRegistrationItemResource::collection($this->whenLoaded('items')),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
