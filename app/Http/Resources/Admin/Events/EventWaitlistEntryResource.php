<?php

namespace App\Http\Resources\Admin\Events;

use App\Models\EventWaitlistEntry;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EventWaitlistEntry */
class EventWaitlistEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'waitlist_id' => 'WA-'.strtoupper(substr($this->uuid, 0, 6)),
            'attendee_name' => $this->attendeeName(),
            'attendee_email' => $this->attendeeEmail(),
            'is_guest' => $this->isGuest(),
            'guest_phone' => $this->guest_phone,
            'ticket_requested' => $this->whenLoaded('ticketType', fn () => $this->ticketType?->name),
            'quantity_requested' => $this->quantity_requested,
            'waitlist_position' => $this->position,
            'projected_value' => (string) $this->projected_value,
            'projected_value_formatted' => Money::format($this->projected_value, $this->currency),
            'status' => $this->status,
            'date_joined_waitlist' => $this->created_at?->toIso8601String(),
        ];
    }
}
