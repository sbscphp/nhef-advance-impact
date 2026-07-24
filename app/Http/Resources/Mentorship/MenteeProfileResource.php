<?php

namespace App\Http\Resources\Mentorship;

use App\Models\MenteeProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MenteeProfile */
class MenteeProfileResource extends JsonResource
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
            'interest_areas' => $this->interest_areas,
            'skills' => $this->skills,
            'professional_summary' => $this->professional_summary,
            'why_mentor_needed' => $this->why_mentor_needed,
            'available_days' => $this->available_days,
            'frequency_of_interaction' => $this->frequency_of_interaction,
            'linkedin_url' => $this->linkedin_url,
            'twitter_url' => $this->twitter_url,
            'portfolio_url' => $this->portfolio_url,
            'active_match' => MentorshipMatchResource::make($this->whenLoaded('activeMatch')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
