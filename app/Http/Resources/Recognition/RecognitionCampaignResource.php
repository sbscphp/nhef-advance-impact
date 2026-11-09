<?php

namespace App\Http\Resources\Recognition;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecognitionCampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'campaign_uuid' => $this->campaign_uuid,
            'campaign_name' => $this->campaign_name,
            'tier' => $this->tier,
            'total' => $this->total,
            'total_formatted' => Money::format($this->total, 'NGN'),
            'position' => $this->position,
            'donors_count' => $this->donors_count,
            'position_change' => $this->position_change,
        ];
    }
}
