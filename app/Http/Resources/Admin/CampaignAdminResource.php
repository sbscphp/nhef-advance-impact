<?php

namespace App\Http\Resources\Admin;

use App\Models\Campaign;
use App\Support\Money;
use App\Http\Resources\Concerns\PresentsCampaignSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Campaign */
class CampaignAdminResource extends JsonResource
{
    use PresentsCampaignSchedule;

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
            'status' => $this->status,
            ...$this->schedulePayload(),
            ...$this->projectsPayload(),
            'cover_media_type' => $this->cover_media_type,
            'creator' => $this->whenLoaded('creator', fn () => $this->creator === null ? null : [
                'admin_id' => $this->creator->uuid,
                'name' => $this->creator->displayName(),
            ]),
            'assigned_to' => $this->assigneePayload(),
            'allocated_admin' => $this->assigneePayload(),
            'bank_account' => $this->whenLoaded('bankAccount', fn () => [
                'bank_account_id' => $this->bankAccount->uuid,
                'account_number' => $this->bankAccount->account_number,
                'account_name' => $this->bankAccount->account_name,
                'bank_name' => $this->bankAccount->relationLoaded('bank') ? $this->bankAccount->bank->name : null,
            ]),
            'donations_count' => $this->when(array_key_exists('donations_count', $this->resource->getAttributes()), fn () => (int) $this->donations_count),
            'donors_count' => $this->when(array_key_exists('donors_count', $this->resource->getAttributes()), fn () => (int) $this->donors_count),
            'institutions_count' => $this->when(array_key_exists('institutions_count', $this->resource->getAttributes()), fn () => (int) $this->institutions_count),
            'share_url' => rtrim((string) config('app.frontend_url'), '/').'/campaigns/'.$this->slug,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
