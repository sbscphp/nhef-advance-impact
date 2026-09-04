<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\TertiaryInstitutionResource;
use App\Models\Pledge;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Pledge */
class CampaignPledgeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'pledge_id' => $this->uuid,
            'donor_name' => $this->donorName(),
            'institution' => $this->whenLoaded('user', fn () => $this->user?->tertiaryInstitution
                ? TertiaryInstitutionResource::make($this->user->tertiaryInstitution)
                : null),
            'total_pledge' => (string) $this->total_amount,
            'total_pledge_formatted' => Money::format($this->total_amount, $this->currency),
            'received' => (string) $this->amount_paid,
            'received_formatted' => Money::format($this->amount_paid, $this->currency),
            'outstanding' => $this->amountRemaining(),
            'outstanding_formatted' => Money::format($this->amountRemaining(), $this->currency),
            'next_installment' => $this->whenLoaded('nextPendingInstallment', fn () => $this->nextPendingInstallment === null ? null : [
                'amount' => (string) $this->nextPendingInstallment->amount,
                'amount_formatted' => Money::format($this->nextPendingInstallment->amount, $this->currency),
                'due_date' => $this->nextPendingInstallment->due_date?->toDateString(),
            ]),
            'status' => $this->status,
        ];
    }
}
