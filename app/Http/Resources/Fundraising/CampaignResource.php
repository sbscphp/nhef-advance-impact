<?php

namespace App\Http\Resources\Fundraising;

use App\Models\Campaign;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Campaign */
class CampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'category' => $this->category,
            'cover_image_url' => $this->cover_image_url,
            'currency' => $this->currency,
            'goal_amount' => (string) $this->goal_amount,
            'goal_amount_formatted' => Money::format($this->goal_amount, $this->currency),
            'raised_amount' => (string) $this->raised_amount,
            'raised_amount_formatted' => Money::format($this->raised_amount, $this->currency),
            'progress_percentage' => $this->progressPercentage(),
            'allow_one_time' => (bool) $this->allow_one_time,
            'allow_recurring' => (bool) $this->allow_recurring,
            'allow_anonymous' => (bool) $this->allow_anonymous,
            'status' => $this->status,
            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
