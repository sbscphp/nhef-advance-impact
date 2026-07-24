<?php

namespace App\Http\Resources\Mentorship;

use App\Models\MentorshipMatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MentorshipMatch */
class MentorshipMatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'mentor' => MentorProfileResource::make($this->whenLoaded('mentorProfile')),
            'matched_at' => $this->matched_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
