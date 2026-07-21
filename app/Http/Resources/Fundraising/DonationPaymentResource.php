<?php

namespace App\Http\Resources\Fundraising;

use App\Enums\PaymentMethodEnum;
use App\Models\DonationPayment;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/** @mixin DonationPayment */
class DonationPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'campaign' => CampaignResource::make($this->whenLoaded('donation', fn () => $this->donation->campaign)),
            'amount' => (string) $this->amount,
            'amount_formatted' => Money::format($this->amount, $this->currency),
            'currency' => $this->currency,
            'method' => $this->method,
            'method_label' => $this->method !== null ? PaymentMethodEnum::from($this->method)->label() : null,
            'gateway_reference' => $this->gateway_reference,
            'status' => $this->status,
            'card_last_four' => $this->card_last_four,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'receipt_url' => $this->status === 'successful'
                ? URL::signedRoute('receipts.download', ['uuid' => $this->uuid])
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
