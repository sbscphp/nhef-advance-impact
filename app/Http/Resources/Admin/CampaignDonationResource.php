<?php

namespace App\Http\Resources\Admin;

use App\Enums\PaymentMethodEnum;
use App\Http\Resources\TertiaryInstitutionResource;
use App\Models\DonationPayment;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DonationPayment */
class CampaignDonationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'donation_payment_id' => $this->uuid,
            'date' => ($this->paid_at ?? $this->created_at)?->toIso8601String(),
            'donor_name' => $this->whenLoaded('donation', fn () => $this->donation->donorName()),
            'donor_email' => $this->whenLoaded('donation', fn () => $this->donation->donorEmail()),
            'institution' => $this->whenLoaded('donation', fn () => $this->donation->user?->tertiaryInstitution
                ? TertiaryInstitutionResource::make($this->donation->user->tertiaryInstitution)
                : null),
            'amount' => (string) $this->amount,
            'amount_formatted' => Money::format($this->amount, $this->currency),
            'method' => $this->method,
            'method_label' => $this->method !== null ? PaymentMethodEnum::from($this->method)->label() : null,
            'status' => $this->status,
        ];
    }
}
