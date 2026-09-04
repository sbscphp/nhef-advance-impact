<?php

namespace App\Http\Resources\Communications;

use App\Models\Mail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Mail */
class MailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'banner_url' => $this->banner_url,
            'body' => $this->body,
            'status' => $this->status,
            'recipient_count' => $this->whenCounted('recipients'),
            'segment' => [
                'tertiary_institution_uuid' => $this->segment_criteria['tertiary_institution_uuid'] ?? null,
                'department' => $this->segment_criteria['department'] ?? null,
                'graduation_year_from' => $this->segment_criteria['graduation_year_from'] ?? null,
                'graduation_year_to' => $this->segment_criteria['graduation_year_to'] ?? null,
            ],
            'picked_recipients' => $this->whenLoaded('pickedRecipients', fn () => $this->pickedRecipients->map(fn ($user) => [
                'uuid' => $user->uuid,
                'name' => $user->displayName(),
                'email' => $user->email,
            ])->values()),
            'send_at' => $this->send_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'sent_by' => $this->whenLoaded('sender', fn () => $this->sender === null ? null : [
                'uuid' => $this->sender->uuid,
                'name' => $this->sender->displayName(),
            ]),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator === null ? null : [
                'uuid' => $this->creator->uuid,
                'name' => $this->creator->displayName(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
