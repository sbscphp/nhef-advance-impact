<?php

namespace App\Http\Resources\Recognition;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaderboardEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'rank' => $this->rank,
            'donor_name' => $this->donor_name,
            'avatar_url' => $this->avatar_url,
            'tier' => $this->tier,
            'total' => $this->total,
            'total_formatted' => Money::format($this->total, 'NGN'),
        ];
    }
}
