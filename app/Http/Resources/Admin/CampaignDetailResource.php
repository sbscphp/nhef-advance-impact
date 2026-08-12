<?php

namespace App\Http\Resources\Admin;

use App\Models\Campaign;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Backs the admin "View Campaign" header. Expects `donors_count` and `days_remaining` already
 * set on the model (see CampaignController@show); both need extra queries the lighter
 * CampaignAdminResource (list/create responses) doesn't run.
 *
 * @mixin Campaign
 */
class CampaignDetailResource extends JsonResource
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
            'cover_image_url' => $this->cover_image_url,
            'type' => $this->type,
            'currency' => $this->currency,
            'goal_amount' => (string) $this->goal_amount,
            'goal_amount_formatted' => Money::format($this->goal_amount, $this->currency),
            'raised_amount' => (string) $this->raised_amount,
            'raised_amount_formatted' => Money::format($this->raised_amount, $this->currency),
            'progress_percentage' => $this->progressPercentage(),
            'status' => $this->status,
            'donors_count' => $this->donors_count,
            'days_remaining' => $this->days_remaining,
            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator === null ? null : [
                'admin_id' => $this->creator->uuid,
                'name' => $this->creator->displayName(),
            ]),
            'allocated_admin' => $this->whenLoaded('allocatedAdmin', fn () => [
                'admin_id' => $this->allocatedAdmin->uuid,
                'name' => $this->allocatedAdmin->displayName(),
            ]),
            'bank_account' => $this->whenLoaded('bankAccount', fn () => [
                'bank_account_id' => $this->bankAccount->uuid,
                'account_number' => $this->bankAccount->account_number,
                'account_name' => $this->bankAccount->account_name,
                'bank_name' => $this->bankAccount->relationLoaded('bank') ? $this->bankAccount->bank->name : null,
            ]),
            'share_url' => rtrim((string) config('app.frontend_url'), '/').'/campaigns/'.$this->slug,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
