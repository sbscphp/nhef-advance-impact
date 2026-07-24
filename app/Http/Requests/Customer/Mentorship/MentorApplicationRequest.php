<?php

namespace App\Http\Requests\Customer\Mentorship;

use App\Enums\MentorshipCommitmentEnum;
use App\Enums\MentorshipFrequencyEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class MentorApplicationRequest extends ApiFormRequest
{
    private const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_job_title' => ['required', 'string', 'max:255'],
            'company' => ['required', 'string', 'max:255'],
            'years_of_experience' => ['required', 'integer', 'min:0', 'max:80'],
            'professional_summary' => ['required', 'string', 'max:2000'],
            'major_achievements' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'expertise_tags' => ['required', 'array', 'min:1', 'max:20'],
            'expertise_tags.*' => ['required', 'string', 'max:100'],
            'guidance_areas' => ['required', 'array', 'min:1', 'max:20'],
            'guidance_areas.*' => ['required', 'string', 'max:100'],
            'max_capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'available_days' => ['required', 'array', 'min:1'],
            'available_days.*' => ['required', Rule::in(self::WEEKDAYS)],
            'frequency_of_interaction' => ['required', Rule::in(MentorshipFrequencyEnum::values())],
            'program_commitment' => ['required', Rule::in(MentorshipCommitmentEnum::values())],
            'linkedin_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'twitter_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'portfolio_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'confirms_experience_requirement' => ['required', 'accepted'],
            'confirms_time_commitment' => ['required', 'accepted'],
            'confirms_ethics_agreement' => ['required', 'accepted'],
        ];
    }
}
