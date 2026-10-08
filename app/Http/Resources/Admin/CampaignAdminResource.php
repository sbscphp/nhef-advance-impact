<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\Concerns\PresentsCampaign;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Campaign */
class CampaignAdminResource extends JsonResource
{
    use PresentsCampaign;

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
            ...$this->amountsPayload(),
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
            ...$this->bankAccountFields(),
            'donations_count' => $this->when(array_key_exists('donations_count', $this->resource->getAttributes()), fn () => (int) $this->donations_count),
            'donors_count' => $this->when(array_key_exists('donors_count', $this->resource->getAttributes()), fn () => (int) $this->donors_count),
            'pledges_count' => $this->when(array_key_exists('pledges_count', $this->resource->getAttributes()), fn () => (int) $this->pledges_count),
            'institutions_count' => $this->when(array_key_exists('institutions_count', $this->resource->getAttributes()), fn () => (int) $this->institutions_count),
            // One per standard campaign, several for a National Giving Day campaign.
            'institutions' => $this->when(array_key_exists('institutions', $this->resource->getAttributes()), fn () => $this->institutions),
            'share_url' => rtrim((string) config('app.frontend_url'), '/').'/campaigns/'.$this->slug,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
