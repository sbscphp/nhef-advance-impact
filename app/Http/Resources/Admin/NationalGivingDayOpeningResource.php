<?php

namespace App\Http\Resources\Admin;

use App\Models\NationalGivingDayOpening;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NationalGivingDayOpening */
class NationalGivingDayOpeningResource extends JsonResource
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
            'cover_media_type' => $this->cover_media_type,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'status' => $this->status,
            'campaigns_count' => $this->when(array_key_exists('campaigns_count', $this->resource->getAttributes()), fn () => (int) $this->campaigns_count),
            'assigned_to' => $this->whenLoaded('allocatedAdmin', fn () => $this->allocatedAdmin === null ? null : [
                'admin_id' => $this->allocatedAdmin->uuid,
                'name' => $this->allocatedAdmin->displayName(),
            ]),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator === null ? null : [
                'admin_id' => $this->creator->uuid,
                'name' => $this->creator->displayName(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
