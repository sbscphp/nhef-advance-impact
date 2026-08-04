<?php

namespace App\Services\Mentorship;

use App\Enums\AuditActionEnum;
use App\Enums\MentorListingStatusEnum;
use App\Enums\MentorReviewStatusEnum;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Models\MenteeProfile;
use App\Models\MentorProfile;
use App\Models\MentorshipMatch;
use App\Models\MentorshipReview;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use App\Repositories\Contracts\Mentorship\MenteeProfileRepositoryInterface;
use App\Repositories\Contracts\Mentorship\MentorProfileRepositoryInterface;
use App\Repositories\Contracts\Mentorship\MentorshipMatchRepositoryInterface;
use App\Repositories\Contracts\Mentorship\MentorshipReviewRepositoryInterface;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MentorshipService
{
    public function __construct(
        private readonly MentorProfileRepositoryInterface $mentorProfileRepository,
        private readonly MenteeProfileRepositoryInterface $menteeProfileRepository,
        private readonly MentorshipMatchRepositoryInterface $matchRepository,
        private readonly MentorshipReviewRepositoryInterface $reviewRepository,
        private readonly MentorMatchingService $matchingService,
        private readonly NotificationDispatchService $notificationDispatchService,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function applyAsMentor(User $user, array $validated, Request $request): MentorProfile
    {
        if ($this->mentorProfileRepository->findByUserId($user->id) !== null) {
            throw new ApiException('You have already applied to be a mentor.', 422);
        }

        $mentor = $this->mentorProfileRepository->create([
            'user_id' => $user->id,
            'current_job_title' => $validated['current_job_title'],
            'company' => $validated['company'],
            'years_of_experience' => $validated['years_of_experience'],
            'professional_summary' => $validated['professional_summary'],
            'major_achievements' => $validated['major_achievements'] ?? null,
            'expertise_tags' => $validated['expertise_tags'],
            'guidance_areas' => $validated['guidance_areas'],
            'max_capacity' => $validated['max_capacity'],
            'available_days' => $validated['available_days'],
            'frequency_of_interaction' => $validated['frequency_of_interaction'],
            'program_commitment' => $validated['program_commitment'],
            'linkedin_url' => $validated['linkedin_url'] ?? null,
            'twitter_url' => $validated['twitter_url'] ?? null,
            'portfolio_url' => $validated['portfolio_url'] ?? null,
            'eligibility_confirmed_at' => now(),
            'review_status' => MentorReviewStatusEnum::PENDING->value,
            'listing_status' => MentorListingStatusEnum::ACTIVE->value,
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::CUSTOMER,
            AuditActionEnum::MENTOR_APPLICATION_CREATED,
            $request,
            $user->uuid,
            ['mentor_profile_uuid' => $mentor->uuid],
            $user->displayName().' applied to become a mentor.',
            MentorProfile::class,
            $mentor->uuid,
            ModuleEnums::mentorship,
            201,
        );

        $this->notifyAdminsOfNewMentorApplication($mentor, $user);

        return $mentor;
    }

    /**
     * Creates the mentee profile and immediately attempts an auto-match. There is no admin
     * approval step for mentees (unlike mentors); the profile is live and matchable right away.
     *
     * @param  array<string, mixed>  $validated
     * @return array{mentee: MenteeProfile, match: ?MentorshipMatch}
     */
    public function applyAsMentee(User $user, array $validated, Request $request): array
    {
        if ($this->menteeProfileRepository->findByUserId($user->id) !== null) {
            throw new ApiException('You have already applied to be a mentee.', 422);
        }

        return DB::transaction(function () use ($user, $validated, $request): array {
            $mentee = $this->menteeProfileRepository->create([
                'user_id' => $user->id,
                'interest_areas' => $validated['interest_areas'],
                'skills' => $validated['skills'],
                'professional_summary' => $validated['professional_summary'],
                'why_mentor_needed' => $validated['why_mentor_needed'],
                'available_days' => $validated['available_days'],
                'frequency_of_interaction' => $validated['frequency_of_interaction'],
                'linkedin_url' => $validated['linkedin_url'] ?? null,
                'twitter_url' => $validated['twitter_url'] ?? null,
                'portfolio_url' => $validated['portfolio_url'] ?? null,
                'consent_confirmed_at' => now(),
            ]);

            GeneralHelper::storeAuditLog(
                UserTypeEnum::CUSTOMER,
                AuditActionEnum::MENTEE_APPLICATION_CREATED,
                $request,
                $user->uuid,
                ['mentee_profile_uuid' => $mentee->uuid],
                $user->displayName().' applied to be mentored.',
                MenteeProfile::class,
                $mentee->uuid,
                ModuleEnums::mentorship,
                201,
            );

            $match = $this->matchingService->match($mentee);

            if ($match !== null) {
                GeneralHelper::storeAuditLog(
                    UserTypeEnum::CUSTOMER,
                    AuditActionEnum::MENTORSHIP_MATCHED,
                    $request,
                    $user->uuid,
                    ['mentorship_match_uuid' => $match->uuid, 'mentor_profile_uuid' => $match->mentorProfile->uuid],
                    $user->displayName().' was matched with a mentor.',
                    MentorshipMatch::class,
                    $match->uuid,
                    ModuleEnums::mentorship,
                    201,
                );

                $this->notifyMatched($match);
            }

            return ['mentee' => $mentee, 'match' => $match];
        });
    }

    public function pauseListing(User $user, Request $request): MentorProfile
    {
        $mentor = $this->findMyMentorProfile($user);

        if ($mentor->listing_status === MentorListingStatusEnum::PAUSED->value) {
            throw new ApiException('Your mentor listing is already paused.', 422);
        }

        $mentor = $this->mentorProfileRepository->update($mentor, ['listing_status' => MentorListingStatusEnum::PAUSED->value]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::CUSTOMER,
            AuditActionEnum::MENTOR_LISTING_PAUSED,
            $request,
            $user->uuid,
            ['mentor_profile_uuid' => $mentor->uuid],
            $user->displayName().' paused their mentor listing.',
            MentorProfile::class,
            $mentor->uuid,
            ModuleEnums::mentorship,
            200,
        );

        return $mentor;
    }

    public function resumeListing(User $user, Request $request): MentorProfile
    {
        $mentor = $this->findMyMentorProfile($user);

        if ($mentor->listing_status === MentorListingStatusEnum::ACTIVE->value) {
            throw new ApiException('Your mentor listing is already active.', 422);
        }

        $mentor = $this->mentorProfileRepository->update($mentor, ['listing_status' => MentorListingStatusEnum::ACTIVE->value]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::CUSTOMER,
            AuditActionEnum::MENTOR_LISTING_RESUMED,
            $request,
            $user->uuid,
            ['mentor_profile_uuid' => $mentor->uuid],
            $user->displayName().' resumed their mentor listing.',
            MentorProfile::class,
            $mentor->uuid,
            ModuleEnums::mentorship,
            200,
        );

        return $mentor;
    }

    public function findMyMentorProfile(User $user): MentorProfile
    {
        $mentor = $this->mentorProfileRepository->findByUserId($user->id);

        if (! $mentor instanceof MentorProfile) {
            throw new ApiException('You have not applied to be a mentor.', 404);
        }

        return $mentor;
    }

    public function findMyMenteeProfile(User $user): MenteeProfile
    {
        $mentee = $this->menteeProfileRepository->findByUserId($user->id);

        if (! $mentee instanceof MenteeProfile) {
            throw new ApiException('You have not applied to be a mentee.', 404);
        }

        return $mentee;
    }

    /** Only approved mentors are visible via their public profile uuid. */
    public function findMentorByUuid(string $uuid): MentorProfile
    {
        $mentor = $this->mentorProfileRepository->findByUuid($uuid);

        if (! $mentor instanceof MentorProfile || $mentor->review_status !== MentorReviewStatusEnum::APPROVED->value) {
            throw new ApiException('Mentor not found.', 404);
        }

        return $mentor;
    }

    public function findMenteeByUuid(string $uuid): MenteeProfile
    {
        $mentee = $this->menteeProfileRepository->findByUuid($uuid);

        if (! $mentee instanceof MenteeProfile) {
            throw new ApiException('Mentee not found.', 404);
        }

        return $mentee;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateMentors(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->mentorProfileRepository->paginateApproved($filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateMentees(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->menteeProfileRepository->paginate($filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function submitReview(User $user, string $mentorUuid, array $validated, Request $request): MentorshipReview
    {
        $mentor = $this->findMentorByUuid($mentorUuid);
        $mentee = $this->findMyMenteeProfile($user);

        $match = $this->matchRepository->findActiveForMentorAndMentee($mentor->id, $mentee->id);

        if (! $match instanceof MentorshipMatch) {
            throw new ApiException('You do not have an active mentorship with this mentor.', 422);
        }

        if ($this->reviewRepository->findForMatch($match->id) !== null) {
            throw new ApiException('You have already reviewed this mentorship.', 422);
        }

        $review = $this->reviewRepository->create([
            'mentorship_match_id' => $match->id,
            'quality_rating' => $validated['quality_rating'],
            'communication_rating' => $validated['communication_rating'],
            'responsiveness_rating' => $validated['responsiveness_rating'],
            'professionalism_rating' => $validated['professionalism_rating'],
            'description' => $validated['description'],
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::CUSTOMER,
            AuditActionEnum::MENTORSHIP_REVIEW_CREATED,
            $request,
            $user->uuid,
            ['mentorship_review_uuid' => $review->uuid, 'mentor_profile_uuid' => $mentor->uuid],
            $user->displayName().' reviewed their mentor.',
            MentorshipReview::class,
            $review->uuid,
            ModuleEnums::mentorship,
            201,
        );

        $this->notifyReviewSubmitted($mentor, $review);

        return $review;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateReviewsForMentor(string $mentorUuid, array $filters): LengthAwarePaginator
    {
        $mentor = $this->findMentorByUuid($mentorUuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->reviewRepository->paginateForMentor($mentor->id, $filters, $perPage);
    }

    private function notifyAdminsOfNewMentorApplication(MentorProfile $mentor, User $user): void
    {
        $notification = new GenericDatabaseNotification(
            module: ModuleEnums::mentorship->value,
            event: 'mentor_application_submitted',
            title: 'New mentor application',
            message: $user->displayName().' applied to become a mentor and is awaiting review.',
            meta: ['mentor_profile_uuid' => $mentor->uuid],
        );

        $this->notificationDispatchService->notifySuperAdmins($notification);
    }

    private function notifyMatched(MentorshipMatch $match): void
    {
        $mentorUser = $match->mentorProfile->user;
        $menteeUser = $match->menteeProfile->user;

        $this->notificationDispatchService->notifyUsersByUuids([$mentorUser->uuid], new GenericDatabaseNotification(
            module: ModuleEnums::mentorship->value,
            event: 'mentorship_matched',
            title: 'You have a new mentee',
            message: $menteeUser->displayName().' has been matched with you as a mentee.',
            meta: ['mentorship_match_uuid' => $match->uuid],
            sendMail: true,
            mailSubject: 'You have a new mentee',
        ));

        $this->notificationDispatchService->notifyUsersByUuids([$menteeUser->uuid], new GenericDatabaseNotification(
            module: ModuleEnums::mentorship->value,
            event: 'mentorship_matched',
            title: 'You have been matched with a mentor',
            message: 'You have been matched with '.$mentorUser->displayName().' as your mentor.',
            meta: ['mentorship_match_uuid' => $match->uuid],
            sendMail: true,
            mailSubject: 'You have been matched with a mentor',
        ));
    }

    private function notifyReviewSubmitted(MentorProfile $mentor, MentorshipReview $review): void
    {
        $notification = new GenericDatabaseNotification(
            module: ModuleEnums::mentorship->value,
            event: 'mentorship_review_submitted',
            title: 'You received a new review',
            message: 'A mentee left you a '.$review->averageRating().'-star review.',
            meta: ['mentorship_review_uuid' => $review->uuid],
            sendMail: true,
            mailSubject: 'You received a new mentorship review',
        );

        $this->notificationDispatchService->notifyUsersByUuids([$mentor->user->uuid], $notification);
    }
}
