<?php

namespace Tests\Feature\Mentorship;

use App\Enums\MentorListingStatusEnum;
use App\Enums\MentorReviewStatusEnum;
use App\Enums\MentorshipMatchedByEnum;
use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Models\MenteeProfile;
use App\Models\MentorProfile;
use App\Models\User;
use App\Services\Mentorship\MentorshipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminMentorshipLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_a_pending_mentor_application(): void
    {
        $admin = $this->makeAdmin();
        $mentor = $this->makeMentorProfile(User::factory()->create());
        $service = app(MentorshipService::class);

        $updated = $service->approveMentorApplication($admin, $mentor->uuid, Request::create('/'));

        $this->assertSame(MentorReviewStatusEnum::APPROVED->value, $updated->review_status);
        $this->assertSame($admin->id, $updated->reviewed_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'MENTOR_APPLICATION_APPROVED']);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Only a pending application can be approved.');
        $service->approveMentorApplication($admin, $mentor->uuid, Request::create('/'));
    }

    public function test_admin_can_reject_a_pending_mentor_application(): void
    {
        $admin = $this->makeAdmin();
        $mentor = $this->makeMentorProfile(User::factory()->create());
        $service = app(MentorshipService::class);

        $updated = $service->rejectMentorApplication($admin, $mentor->uuid, 'Insufficient experience.', Request::create('/'));

        $this->assertSame(MentorReviewStatusEnum::REJECTED->value, $updated->review_status);
        $this->assertSame('Insufficient experience.', $updated->rejection_reason);
        $this->assertDatabaseHas('audit_logs', ['action' => 'MENTOR_APPLICATION_REJECTED']);

        $reactivated = $service->reactivateMentor($admin, $mentor->uuid, Request::create('/'));

        $this->assertSame(MentorReviewStatusEnum::APPROVED->value, $reactivated->review_status);
        $this->assertSame(MentorListingStatusEnum::ACTIVE->value, $reactivated->listing_status);
        $this->assertNull($reactivated->rejection_reason);
    }

    public function test_admin_can_suspend_and_reactivate_an_approved_mentor(): void
    {
        $admin = $this->makeAdmin();
        $mentor = $this->makeMentorProfile(User::factory()->create(), [
            'review_status' => MentorReviewStatusEnum::APPROVED->value,
        ]);
        $service = app(MentorshipService::class);

        $suspended = $service->suspendMentor($admin, $mentor->uuid, 'Repeated no-shows.', Request::create('/'));

        $this->assertSame(MentorListingStatusEnum::SUSPENDED->value, $suspended->listing_status);
        $this->assertSame('Repeated no-shows.', $suspended->suspension_reason);
        $this->assertFalse($suspended->isAvailableForMatching());
        $this->assertDatabaseHas('audit_logs', ['action' => 'MENTOR_SUSPENDED']);

        $reactivated = $service->reactivateMentor($admin, $mentor->uuid, Request::create('/'));

        $this->assertSame(MentorListingStatusEnum::ACTIVE->value, $reactivated->listing_status);
        $this->assertNull($reactivated->suspension_reason);
        $this->assertTrue($reactivated->isAvailableForMatching());
        $this->assertDatabaseHas('audit_logs', ['action' => 'MENTOR_REACTIVATED']);
    }

    public function test_admin_can_manually_match_an_unmatched_mentee_with_a_recommended_mentor(): void
    {
        $admin = $this->makeAdmin();
        $mentor = $this->makeMentorProfile(User::factory()->create(), [
            'review_status' => MentorReviewStatusEnum::APPROVED->value,
            'guidance_areas' => ['product management', 'career growth'],
        ]);
        $mentee = $this->makeMenteeProfile(User::factory()->create(), [
            'interest_areas' => ['product management'],
        ]);
        $service = app(MentorshipService::class);

        $recommendations = $service->recommendMentorsForMentee($mentee->uuid);
        $this->assertNotEmpty($recommendations);
        $this->assertSame($mentor->id, $recommendations[0]['mentor']->id);
        $this->assertGreaterThan(0, $recommendations[0]['score']);

        $match = $service->matchManually($admin, $mentor->uuid, $mentee->uuid, Request::create('/'));

        $this->assertSame(MentorshipMatchedByEnum::ADMIN->value, $match->matched_by);
        $this->assertDatabaseHas('mentorship_matches', [
            'mentor_profile_id' => $mentor->id,
            'mentee_profile_id' => $mentee->id,
            'matched_by' => MentorshipMatchedByEnum::ADMIN->value,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'MENTORSHIP_MATCHED_MANUALLY']);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('This mentee already has an active mentor match.');
        $service->matchManually($admin, $mentor->uuid, $mentee->uuid, Request::create('/'));
    }

    public function test_chat_channel_for_match_reuses_existing_networking_direct_conversation(): void
    {
        $admin = $this->makeAdmin();
        $mentorUser = User::factory()->create();
        $menteeUser = User::factory()->create();
        $mentor = $this->makeMentorProfile($mentorUser, ['review_status' => MentorReviewStatusEnum::APPROVED->value]);
        $mentee = $this->makeMenteeProfile($menteeUser);
        $service = app(MentorshipService::class);

        $match = $service->matchManually($admin, $mentor->uuid, $mentee->uuid, Request::create('/'));

        $channel = $service->chatChannelForMatch($match->uuid);
        $sameChannel = $service->chatChannelForMatch($match->uuid);

        $this->assertSame($channel->id, $sameChannel->id);
        $this->assertDatabaseHas('networking_channel_members', ['channel_id' => $channel->id, 'user_id' => $mentorUser->id]);
        $this->assertDatabaseHas('networking_channel_members', ['channel_id' => $channel->id, 'user_id' => $menteeUser->id]);
    }

    private function makeAdmin(): Admin
    {
        return Admin::create([
            'name' => 'Super Admin',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_active' => true,
            'can_login' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeMentorProfile(User $user, array $overrides = []): MentorProfile
    {
        return MentorProfile::create(array_merge([
            'user_id' => $user->id,
            'current_job_title' => 'Product Manager',
            'company' => 'Acme Corp',
            'years_of_experience' => 8,
            'professional_summary' => 'Experienced product manager.',
            'expertise_tags' => ['product management'],
            'guidance_areas' => ['product management'],
            'max_capacity' => 5,
            'available_days' => ['monday', 'wednesday'],
            'frequency_of_interaction' => 'weekly',
            'program_commitment' => '6_months',
            'eligibility_confirmed_at' => now(),
            'review_status' => MentorReviewStatusEnum::PENDING->value,
            'listing_status' => MentorListingStatusEnum::ACTIVE->value,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeMenteeProfile(User $user, array $overrides = []): MenteeProfile
    {
        return MenteeProfile::create(array_merge([
            'user_id' => $user->id,
            'interest_areas' => ['product management'],
            'skills' => ['communication'],
            'professional_summary' => 'Aspiring product manager.',
            'why_mentor_needed' => 'Looking to grow my career.',
            'available_days' => ['monday', 'wednesday'],
            'frequency_of_interaction' => 'weekly',
            'consent_confirmed_at' => now(),
        ], $overrides));
    }
}
