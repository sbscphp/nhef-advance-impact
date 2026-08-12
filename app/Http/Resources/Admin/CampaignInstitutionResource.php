<?php

namespace App\Http\Resources\Admin;

use App\Models\CampaignInstitution;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Backs the "Institutions" tab of a National Giving Day campaign. Expects `raised_amount`
 * already set on the model (see CampaignService::listInstitutions()) - it's computed live from
 * donations/pledges, not stored on the row.
 *
 * @mixin CampaignInstitution
 */
class CampaignInstitutionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $raisedAmount = (string) ($this->raised_amount ?? '0');

        return [
            'uuid' => $this->uuid,
            'institution' => $this->whenLoaded('institution', fn () => [
                'uuid' => $this->institution->uuid,
                'name' => $this->institution->name,
            ]),
            'goal_amount' => (string) $this->goal_amount,
            'goal_amount_formatted' => Money::format($this->goal_amount, $this->currency),
            'currency' => $this->currency,
            'raised_amount' => $raisedAmount,
            'raised_amount_formatted' => Money::format($raisedAmount, $this->currency),
            'progress_percentage' => $this->progressPercentage($raisedAmount),
            'bank_account' => $this->whenLoaded('bankAccount', fn () => $this->bankAccount === null ? null : [
                'bank_account_id' => $this->bankAccount->uuid,
                'account_number' => $this->bankAccount->account_number,
                'account_name' => $this->bankAccount->account_name,
                'bank_name' => $this->bankAccount->relationLoaded('bank') ? $this->bankAccount->bank->name : null,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
