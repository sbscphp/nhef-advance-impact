<?php

namespace App\Http\Resources\Admin\Events;

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
            'price' => (string) $this->price,
            'price_formatted' => Money::format($this->price, $this->currency),
            'currency' => $this->currency,
            'quantity' => $this->quantity,
            'quantity_sold' => $this->quantity_sold,
            'quantity_remaining' => $this->quantityRemaining(),
            'discount_percentage' => $this->discount_percentage !== null ? (string) $this->discount_percentage : null,
            'discount_starts_at' => $this->discount_starts_at?->toIso8601String(),
            'discount_ends_at' => $this->discount_ends_at?->toIso8601String(),
            'is_available' => $this->isAvailable(),
        ];
    }
}
