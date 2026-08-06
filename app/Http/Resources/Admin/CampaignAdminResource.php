<?php

namespace App\Http\Resources\Admin;

use App\Models\Campaign;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Campaign */
class CampaignAdminResource extends JsonResource
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
            'currency' => $this->currency,
            'goal_amount' => (string) $this->goal_amount,
            'goal_amount_formatted' => Money::format($this->goal_amount, $this->currency),
            'raised_amount' => (string) $this->raised_amount,
            'status' => $this->status,
            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
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
