<?php

namespace App\Http\Requests\Customer\Mentorship;

use App\Enums\MentorshipFrequencyEnum;
use App\Enums\SocialPlatformEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class MenteeApplicationRequest extends ApiFormRequest
{
    private const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'interest_areas' => ['required', 'array', 'min:1', 'max:20'],
            'interest_areas.*' => ['required', 'string', 'max:100'],
            'skills' => ['required', 'array', 'min:1', 'max:20'],
            'skills.*' => ['required', 'string', 'max:100'],
            'professional_summary' => ['required', 'string', 'max:2000'],
            'why_mentor_needed' => ['required', 'string', 'max:2000'],
            'available_days' => ['required', 'array', 'min:1'],
            'available_days.*' => ['required', Rule::in(self::WEEKDAYS)],
            'frequency_of_interaction' => ['required', Rule::in(MentorshipFrequencyEnum::values())],
            'socials' => ['sometimes', 'nullable', 'array', 'max:'.count(SocialPlatformEnum::values())],
            'socials.*.platform' => ['required', Rule::in(SocialPlatformEnum::values()), 'distinct'],
            'socials.*.value' => ['required', 'string', 'max:255'],
            'portfolio_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'confirms_information_accuracy' => ['required', 'accepted'],
            'confirms_matching_understanding' => ['required', 'accepted'],
            'confirms_active_participation' => ['required', 'accepted'],
        ];
    }
}
