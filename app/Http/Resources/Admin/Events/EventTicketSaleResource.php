<?php

namespace App\Http\Resources\Admin\Events;

use App\Models\EventRegistration;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A "Ticket Sales" transaction row: one per completed {@see EventRegistration} (a checkout),
 * not one per ticket-type line item.
 *
 * @mixin EventRegistration
 */
class EventTicketSaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->whenLoaded('items', fn () => $this->items, collect());
        $ticketTypeNames = $items instanceof \Illuminate\Support\Collection
            ? $items->map(fn ($item) => $item->ticketType?->name)->filter()->unique()->values()
            : collect();

        $successfulPayment = $this->whenLoaded('payments', fn () => $this->payments->firstWhere('status', 'successful'));

        return [
            'uuid' => $this->uuid,
            'transaction_id' => 'TRN-'.strtoupper(substr($this->uuid, 0, 8)),
            'purchase_date' => $this->completed_at?->toIso8601String(),
            'ticket_type' => $ticketTypeNames->count() === 1 ? $ticketTypeNames->first() : ($ticketTypeNames->count() > 1 ? 'Multiple' : null),
            'number_of_tickets' => $items instanceof \Illuminate\Support\Collection ? (int) $items->sum('quantity') : null,
            'amount' => (string) $this->amount,
            'amount_formatted' => Money::format($this->amount, $this->currency),
            'currency' => $this->currency,
            'status' => $this->status,
            'attendee_name' => $this->attendeeName(),
            'attendee_email' => $this->attendeeEmail(),
            'is_guest' => $this->isGuest(),
            'paid_via' => $successfulPayment !== null ? ($successfulPayment->method ?? $successfulPayment->gateway) : null,
            'items' => EventTicketSaleItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
