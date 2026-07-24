<?php

namespace App\Http\Resources\Events;

use App\Models\EventRegistrationItem;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EventRegistrationItem */
class EventRegistrationItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'ticket_type' => EventTicketTypeResource::make($this->whenLoaded('ticketType')),
            'quantity' => $this->quantity,
            'unit_price' => (string) $this->unit_price,
            'unit_price_formatted' => Money::format($this->unit_price, $this->currency),
            'subtotal' => (string) $this->subtotal,
            'subtotal_formatted' => Money::format($this->subtotal, $this->currency),
        ];
    }
}
