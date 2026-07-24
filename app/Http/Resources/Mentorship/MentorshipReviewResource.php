<?php

namespace App\Http\Resources\Mentorship;

use App\Models\MentorshipReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MentorshipReview */
class MentorshipReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $mentee = $this->match?->menteeProfile?->user;

        return [
            'uuid' => $this->uuid,
            'reviewer_name' => $mentee?->displayName(),
            'quality_rating' => $this->quality_rating,
            'communication_rating' => $this->communication_rating,
            'responsiveness_rating' => $this->responsiveness_rating,
            'professionalism_rating' => $this->professionalism_rating,
            'average_rating' => $this->averageRating(),
            'description' => $this->description,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
