<?php

namespace App\Http\Resources\Events;

use App\Enums\PaymentMethodEnum;
use App\Models\EventRegistrationPayment;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EventRegistrationPayment */
class EventRegistrationPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'amount' => (string) $this->amount,
            'amount_formatted' => Money::format($this->amount, $this->currency),
            'currency' => $this->currency,
            'method' => $this->method,
            'method_label' => $this->method !== null ? PaymentMethodEnum::from($this->method)->label() : null,
            'gateway_reference' => $this->gateway_reference,
            'status' => $this->status,
            'card_last_four' => $this->card_last_four,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
