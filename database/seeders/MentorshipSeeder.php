<?php

namespace Database\Seeders;

use App\Enums\eRole;
use App\Enums\MentorListingStatusEnum;
use App\Enums\MentorReviewStatusEnum;
use App\Enums\MentorshipCommitmentEnum;
use App\Enums\MentorshipFrequencyEnum;
use App\Enums\MentorshipMatchedByEnum;
use App\Enums\MentorshipMatchStatusEnum;
use App\Enums\SocialPlatformEnum;
use App\Models\MenteeProfile;
use App\Models\MentorProfile;
use App\Models\MentorshipMatch;
use App\Models\MentorshipReview;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A mentor, a mentee, and a completed match with a review, for local development and
 * Postman testing. Mentor/mentee users: nhef-mentor-1@yopmail.com, nhef-mentee-1@yopmail.com
 * (password: "password").
 *
 * php artisan db:seed --class=MentorshipSeeder
 */
class MentorshipSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        $mentorUser = $this->createCustomer('nhef-mentor-1@yopmail.com', 'Mentor', 'One', [
            'matric_no' => 'NHEF/2010/001',
            'university' => 'University of Lagos',
            'department' => 'Computer Science',
            'year_of_graduation' => 2010,
            'degree_earned' => 'B.Sc. Computer Science',
            'employment_status' => 'Employed',
            'organisation_name' => 'Stellar Tech',
            'position' => 'Engineering Manager',
        ]);
        $menteeUser = $this->createCustomer('nhef-mentee-1@yopmail.com', 'Mentee', 'One', [
            'matric_no' => 'NHEF/2020/045',
            'university' => 'University of Ibadan',
            'department' => 'Computer Science',
            'year_of_graduation' => 2020,
            'degree_earned' => 'B.Sc. Computer Science',
            'employment_status' => 'Employed',
            'organisation_name' => 'Fintech Innovations Ltd',
            'position' => 'Backend Developer',
        ]);

        $mentorProfile = MentorProfile::firstOrCreate(
            ['user_id' => $mentorUser->id],
            [
                'current_job_title' => 'Engineering Manager',
                'company' => 'Stellar Tech',
                'years_of_experience' => 12,
                'professional_summary' => 'Engineering leader with a background in fintech and distributed systems.',
                'major_achievements' => 'Scaled a payments platform from zero to 2M daily transactions.',
                'expertise_tags' => ['Software Engineering', 'Engineering Leadership', 'Fintech'],
                'guidance_areas' => ['Career growth', 'Technical interviews', 'People management'],
                'max_capacity' => 5,
                'current_mentee_count' => 1,
                'available_days' => ['Tuesday', 'Thursday'],
                'frequency_of_interaction' => MentorshipFrequencyEnum::BI_WEEKLY->value,
                'program_commitment' => MentorshipCommitmentEnum::SIX_MONTHS->value,
                'socials' => [
                    ['platform' => SocialPlatformEnum::LINKEDIN->value, 'value' => 'https://linkedin.com/in/nhef-mentor-1'],
                ],
                'eligibility_confirmed_at' => now()->subMonth(),
                'review_status' => MentorReviewStatusEnum::APPROVED->value,
                'listing_status' => MentorListingStatusEnum::ACTIVE->value,
                'reviewed_at' => now()->subMonth(),
            ]
        );

        $menteeProfile = MenteeProfile::firstOrCreate(
            ['user_id' => $menteeUser->id],
            [
                'interest_areas' => ['Software Engineering', 'Career growth'],
                'skills' => ['JavaScript', 'PHP'],
                'professional_summary' => 'Early-career backend developer looking to grow into a senior role.',
                'why_mentor_needed' => 'Want guidance on technical interviews and career progression.',
                'available_days' => ['Tuesday', 'Thursday'],
                'frequency_of_interaction' => MentorshipFrequencyEnum::BI_WEEKLY->value,
                'socials' => [
                    ['platform' => SocialPlatformEnum::LINKEDIN->value, 'value' => 'https://linkedin.com/in/nhef-mentee-1'],
                ],
                'consent_confirmed_at' => now()->subMonths(6),
            ]
        );

        $match = MentorshipMatch::firstOrCreate(
            [
                'mentor_profile_id' => $mentorProfile->id,
                'mentee_profile_id' => $menteeProfile->id,
            ],
            [
                'status' => MentorshipMatchStatusEnum::COMPLETED->value,
                'matched_by' => MentorshipMatchedByEnum::SYSTEM->value,
                'matched_at' => now()->subMonths(6),
                'completed_at' => now(),
            ]
        );

        MentorshipReview::firstOrCreate(
            ['mentorship_match_id' => $match->id],
            [
                'quality_rating' => 5,
                'communication_rating' => 5,
                'responsiveness_rating' => 4,
                'professionalism_rating' => 5,
                'description' => 'Excellent mentor, very responsive and gave practical career advice.',
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $alumniFields
     */
    private function createCustomer(string $email, string $firstname, string $lastname, array $alumniFields = []): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            array_merge([
                'firstname' => $firstname,
                'lastname' => $lastname,
                'phone_number' => '198765432',
                'country_code' => '+234',
                '2fa' => false,
                'is_active' => true,
                'can_login' => true,
                'password' => Hash::make(self::PASSWORD),
            ], $alumniFields)
        );

        $user->syncRoles([eRole::CUSTOMER->value]);

        return $user;
    }
}
