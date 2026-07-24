<?php

namespace App\Http\Resources\Events;

use App\Models\Event;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lowestPrice = $this->lowestTicketPrice();
        $isFree = $lowestPrice === null || (float) $lowestPrice === 0.0;

        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'cover_image_url' => $this->cover_image_url,
            'organizer_name' => config('organization.foundation_name'),
            'venue_name' => $this->venue_name,
            'venue_address' => $this->venue_address,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'registration_ends_at' => $this->registration_ends_at?->toIso8601String(),
            'capacity' => $this->capacity,
            'seats_remaining' => $this->seatsRemaining(),
            'status' => $this->status,
            'timeline_status' => $this->timelineStatus(),
            'is_free' => $isFree,
            'starting_price_formatted' => $isFree ? 'Free' : Money::format($lowestPrice, 'NGN'),
            'ticket_types' => EventTicketTypeResource::collection($this->whenLoaded('ticketTypes')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
