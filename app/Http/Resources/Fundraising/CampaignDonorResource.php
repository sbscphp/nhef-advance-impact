<?php

namespace App\Http\Resources\Fundraising;

use App\Models\DonationPayment;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DonationPayment */
class CampaignDonorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'donor_name' => $this->donation->donorName(),
            'amount' => (string) $this->amount,
            'amount_formatted' => Money::format($this->amount, $this->currency),
            'currency' => $this->currency,
            'paid_at' => $this->paid_at?->toIso8601String(),
        ];
    }
}
