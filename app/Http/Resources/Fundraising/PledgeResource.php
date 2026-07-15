<?php

namespace App\Http\Resources\Fundraising;

use App\Models\Pledge;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Pledge */
class PledgeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'campaign' => CampaignResource::make($this->whenLoaded('campaign')),
            'frequency' => $this->frequency,
            'currency' => $this->currency,
            'total_amount' => (string) $this->total_amount,
            'total_amount_formatted' => Money::format($this->total_amount, $this->currency),
            'amount_paid' => (string) $this->amount_paid,
            'amount_paid_formatted' => Money::format($this->amount_paid, $this->currency),
            'amount_remaining' => $this->amountRemaining(),
            'amount_remaining_formatted' => Money::format($this->amountRemaining(), $this->currency),
            'progress_percentage' => $this->progressPercentage(),
            'installment_count' => $this->installment_count,
            'status' => $this->status,
            'is_anonymous' => (bool) $this->is_anonymous,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'next_installment_due_at' => $this->next_installment_due_at?->toDateString(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'installments' => PledgeInstallmentResource::collection($this->whenLoaded('installments')),
            'payments' => PledgePaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
