<?php

namespace App\Http\Resources\Mentorship;

use App\Models\MentorProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MentorProfile */
class MentorProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'user' => $this->whenLoaded('user', fn () => [
                'uuid' => $this->user->uuid,
                'name' => $this->user->displayName(),
                'firstname' => $this->user->firstname,
                'lastname' => $this->user->lastname,
                'email' => $this->user->email,
                'phone_number' => $this->user->phone_number,
                'country_code' => $this->user->country_code,
                'profile_picture_url' => $this->user->profile_picture_url,
                'matric_no' => $this->user->matric_no,
                'university' => $this->user->university,
                'department' => $this->user->department,
                'year_of_graduation' => $this->user->year_of_graduation,
                'degree_earned' => $this->user->degree_earned,
                'employment_status' => $this->user->employment_status,
                'organisation_name' => $this->user->organisation_name,
                'position' => $this->user->position,
            ]),
            'current_job_title' => $this->current_job_title,
            'company' => $this->company,
            'years_of_experience' => $this->years_of_experience,
            'professional_summary' => $this->professional_summary,
            'major_achievements' => $this->major_achievements,
            'expertise_tags' => $this->expertise_tags,
            'guidance_areas' => $this->guidance_areas,
            'max_capacity' => $this->max_capacity,
            'current_mentee_count' => $this->current_mentee_count,
            'available_days' => $this->available_days,
            'frequency_of_interaction' => $this->frequency_of_interaction,
            'program_commitment' => $this->program_commitment,
            'linkedin_url' => $this->linkedin_url,
            'twitter_url' => $this->twitter_url,
            'portfolio_url' => $this->portfolio_url,
            'review_status' => $this->review_status,
            'listing_status' => $this->listing_status,
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->displayName()),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'suspended_by' => $this->whenLoaded('suspendedBy', fn () => $this->suspendedBy?->displayName()),
            'suspended_at' => $this->suspended_at?->toIso8601String(),
            'suspension_reason' => $this->suspension_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
