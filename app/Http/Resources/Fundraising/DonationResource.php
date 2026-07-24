<?php

namespace App\Http\Resources\Fundraising;

use App\Models\Donation;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Donation */
class DonationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'campaign' => CampaignResource::make($this->whenLoaded('campaign')),
            'is_guest' => $this->isGuest(),
            'donor_name' => $this->donorName(),
            'frequency' => $this->frequency,
            'is_recurring' => $this->isRecurring(),
            'currency' => $this->currency,
            'amount' => (string) $this->amount,
            'amount_formatted' => Money::format($this->amount, $this->currency),
            'total_received' => (string) $this->total_received,
            'total_received_formatted' => Money::format($this->total_received, $this->currency),
            'status' => $this->status,
            'is_anonymous' => (bool) $this->is_anonymous,
            'next_charge_at' => $this->next_charge_at?->toDateString(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'paused_at' => $this->paused_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'payments' => DonationPaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
