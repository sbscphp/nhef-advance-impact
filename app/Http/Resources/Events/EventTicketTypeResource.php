<?php

namespace App\Http\Resources\Events;

use App\Models\EventTicketType;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EventTicketType */
class EventTicketTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (string) $this->price,
            'price_formatted' => Money::format($this->price, $this->currency),
            'currency' => $this->currency,
            'quantity' => $this->quantity,
            'quantity_remaining' => $this->quantityRemaining(),
            'sales_close_at' => $this->sales_close_at?->toIso8601String(),
            'is_available' => $this->isAvailable(),
        ];
    }
}
