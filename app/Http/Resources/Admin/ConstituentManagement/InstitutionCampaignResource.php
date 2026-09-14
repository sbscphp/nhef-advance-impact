<?php

namespace App\Http\Resources\Admin\ConstituentManagement;

use App\Models\CampaignInstitution;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects `raised_amount` already set on the model (see AdminInstitutionService::campaigns()).
 *
 * @mixin CampaignInstitution
 */
class InstitutionCampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $raisedAmount = (string) ($this->raised_amount ?? '0');

        return [
            'uuid' => $this->uuid,
            'campaign' => $this->whenLoaded('campaign', fn () => [
                'uuid' => $this->campaign->uuid,
                'title' => $this->campaign->title,
                'status' => $this->campaign->status,
            ]),
            'goal_amount' => (string) $this->goal_amount,
            'goal_amount_formatted' => Money::format($this->goal_amount, $this->currency),
            'currency' => $this->currency,
            'raised_amount' => $raisedAmount,
            'raised_amount_formatted' => Money::format($raisedAmount, $this->currency),
            'progress_percentage' => $this->progressPercentage($raisedAmount),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
