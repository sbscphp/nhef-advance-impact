<?php

namespace App\Http\Resources\Admin;

use App\Models\Campaign;
use App\Support\Money;
use App\Support\ViewerVisibility;
use App\Http\Resources\Concerns\PresentsCampaign;
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
            ...$this->amountsPayload(withProgress: true),
            'status' => $this->status,
            'donors_count' => $this->donors_count,
            'days_remaining' => $this->days_remaining,
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
            'overview' => $this->when(array_key_exists('overview', $this->resource->getAttributes()), fn () => $this->overviewPayload()),
            'share_url' => rtrim((string) config('app.frontend_url'), '/').'/campaigns/'.$this->slug,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function overviewPayload(): array
    {
        $overview = $this->overview;

        return [
            'donations_count' => $overview['donations_count'],
            'pledges_count' => $overview['pledges_count'],
            ...ViewerVisibility::money([
                'amount_generated' => $overview['amount_generated'],
                'amount_generated_formatted' => Money::format($overview['amount_generated'], $this->currency ?? 'NGN'),
                'pledges_total' => $overview['pledges_total'],
                'pledges_total_formatted' => Money::format($overview['pledges_total'], $this->currency ?? 'NGN'),
            ]),
        ];
    }
}
