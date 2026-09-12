<?php

namespace App\Http\Resources\Admin\Projects;

use App\Enums\ProjectBroadcastDeliveryEnum;
use App\Models\ProjectBroadcast;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProjectBroadcast */
class ProjectBroadcastResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'send_date' => $this->send_date?->toDateString(),
            'delivery_via' => $this->delivery_via,
            'delivery_via_label' => ProjectBroadcastDeliveryEnum::from($this->delivery_via)->label(),
            'message' => $this->message,
            'all_team_members' => (bool) $this->all_team_members,
            'attachment_urls' => $this->attachment_urls,
            'number_of_reach' => $this->number_of_reach,
            'recipients' => $this->whenLoaded('recipients', fn () => $this->recipients->map(fn ($admin) => [
                'uuid' => $admin->uuid,
                'name' => $admin->displayName(),
            ])),
            'sent_by' => $this->whenLoaded('sender', fn () => $this->sender === null ? null : [
                'uuid' => $this->sender->uuid,
                'name' => $this->sender->displayName(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
