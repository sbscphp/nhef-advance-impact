<?php

namespace App\Http\Resources\Admin\Events;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
class EventDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'cover_image_url' => $this->cover_image_url,
            'event_type' => $this->event_type,
            'venue_name' => $this->venue_name,
            'venue_address' => $this->venue_address,
            'virtual_link' => $this->virtual_link,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'registration_ends_at' => $this->registration_ends_at?->toIso8601String(),
            'capacity' => $this->capacity,
            'seats_taken' => $this->seats_taken,
            'seats_remaining' => $this->seatsRemaining(),
            'status' => $this->status,
            'display_status' => $this->displayStatus(),
            'waitlist_enabled' => $this->waitlist_enabled,
            'ticket_types' => EventTicketTypeResource::collection($this->whenLoaded('ticketTypes')),
            'share_url' => rtrim((string) config('app.frontend_url'), '/').'/events/'.$this->slug,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
