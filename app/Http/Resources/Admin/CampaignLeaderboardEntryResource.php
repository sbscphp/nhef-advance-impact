<?php

namespace App\Http\Resources\Admin;

use App\Support\ViewerVisibility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignLeaderboardEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'rank' => $this->rank,
            'donor_code' => $this->donor_code,
            'donor_name' => $this->donor_name,
            'avatar_url' => $this->avatar_url,
            'user_type' => $this->user_type,
            'user_type_label' => $this->user_type_label,
            'tier' => $this->tier,
            'tier_color' => $this->tier_color,
            ...ViewerVisibility::money([
                'amount' => $this->amount,
                'amount_formatted' => $this->amount_formatted,
            ]),
        ];
    }
}
