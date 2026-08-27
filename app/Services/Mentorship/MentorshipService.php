<?php

namespace App\Services\Mentorship;

use App\Enums\AuditActionEnum;
use App\Enums\MentorListingStatusEnum;
use App\Enums\MentorReviewStatusEnum;
use App\Enums\MentorshipMatchedByEnum;
use App\Enums\MentorshipMatchStatusEnum;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Models\Admin;
use App\Models\MenteeProfile;
use App\Models\MentorProfile;
use App\Models\MentorshipMatch;
use App\Models\MentorshipReview;
use App\Models\NetworkingChannel;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use App\Repositories\Contracts\Mentorship\MenteeProfileRepositoryInterface;
use App\Repositories\Contracts\Mentorship\MentorProfileRepositoryInterface;
use App\Repositories\Contracts\Mentorship\MentorshipMatchRepositoryInterface;
use App\Repositories\Contracts\Mentorship\MentorshipReviewRepositoryInterface;
use App\Services\Networking\NetworkingService;
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
        private readonly NetworkingService $networkingService,
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
            'socials' => $validated['socials'] ?? null,
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
                'socials' => $validated['socials'] ?? null,
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

    /** Unlike {@see findMentorByUuid()}, admins may view a mentor in any review status. */
    public function findMentorForAdmin(string $uuid): MentorProfile
    {
        $mentor = $this->mentorProfileRepository->findByUuid($uuid);

        if (! $mentor instanceof MentorProfile) {
            throw new ApiException('Mentor not found.', 404);
        }

        return $mentor;
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
     * All mentors regardless of review status, for the admin "Applications" listing.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateMentorsForAdmin(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->mentorProfileRepository->paginateForAdmin($filters, $perPage);
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

    public function approveMentorApplication(Admin $actor, string $uuid, Request $request): MentorProfile
    {
        $mentor = $this->findMentorForAdmin($uuid);

        if ($mentor->review_status !== MentorReviewStatusEnum::PENDING->value) {
            throw new ApiException('Only a pending application can be approved.', 422);
        }

        $mentor = $this->mentorProfileRepository->update($mentor, [
            'review_status' => MentorReviewStatusEnum::APPROVED->value,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::MENTOR_APPLICATION_APPROVED,
            $request,
            $actor->uuid,
            ['mentor_profile_uuid' => $mentor->uuid],
            $actor->displayName().' approved a mentor application: '.$mentor->user->displayName().'.',
            MentorProfile::class,
            $mentor->uuid,
            ModuleEnums::mentorship,
            200,
        );

        $this->notificationDispatchService->notifyUsersByUuids([$mentor->user->uuid], new GenericDatabaseNotification(
            module: ModuleEnums::mentorship->value,
            event: 'mentor_application_approved',
            title: 'Your mentor application was approved',
            message: 'You are now a listed mentor and may be matched with mentees.',
            meta: ['mentor_profile_uuid' => $mentor->uuid],
            sendMail: true,
            mailSubject: 'Your mentor application was approved',
        ));

        return $mentor;
    }

    public function rejectMentorApplication(Admin $actor, string $uuid, string $reason, Request $request): MentorProfile
    {
        $mentor = $this->findMentorForAdmin($uuid);

        if ($mentor->review_status !== MentorReviewStatusEnum::PENDING->value) {
            throw new ApiException('Only a pending application can be rejected.', 422);
        }

        $mentor = $this->mentorProfileRepository->update($mentor, [
            'review_status' => MentorReviewStatusEnum::REJECTED->value,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::MENTOR_APPLICATION_REJECTED,
            $request,
            $actor->uuid,
            ['mentor_profile_uuid' => $mentor->uuid, 'reason' => $reason],
            $actor->displayName().' rejected a mentor application: '.$mentor->user->displayName().'.',
            MentorProfile::class,
            $mentor->uuid,
            ModuleEnums::mentorship,
            200,
        );

        $this->notificationDispatchService->notifyUsersByUuids([$mentor->user->uuid], new GenericDatabaseNotification(
            module: ModuleEnums::mentorship->value,
            event: 'mentor_application_rejected',
            title: 'Your mentor application was not approved',
            message: $reason,
            meta: ['mentor_profile_uuid' => $mentor->uuid],
            sendMail: true,
            mailSubject: 'Your mentor application was not approved',
        ));

        return $mentor;
    }

    /** Only an approved, currently-listed mentor can be suspended; use {@see reactivateMentor()} to undo. */
    public function suspendMentor(Admin $actor, string $uuid, string $reason, Request $request): MentorProfile
    {
        $mentor = $this->findMentorForAdmin($uuid);

        if ($mentor->review_status !== MentorReviewStatusEnum::APPROVED->value) {
            throw new ApiException('Only an approved mentor can be suspended.', 422);
        }

        if ($mentor->listing_status === MentorListingStatusEnum::SUSPENDED->value) {
            throw new ApiException('This mentor is already suspended.', 422);
        }

        $mentor = $this->mentorProfileRepository->update($mentor, [
            'listing_status' => MentorListingStatusEnum::SUSPENDED->value,
            'suspended_by' => $actor->id,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::MENTOR_SUSPENDED,
            $request,
            $actor->uuid,
            ['mentor_profile_uuid' => $mentor->uuid, 'reason' => $reason],
            $actor->displayName().' suspended mentor: '.$mentor->user->displayName().'.',
            MentorProfile::class,
            $mentor->uuid,
            ModuleEnums::mentorship,
            200,
        );

        $this->notificationDispatchService->notifyUsersByUuids([$mentor->user->uuid], new GenericDatabaseNotification(
            module: ModuleEnums::mentorship->value,
            event: 'mentor_suspended',
            title: 'Your mentor listing was suspended',
            message: $reason,
            meta: ['mentor_profile_uuid' => $mentor->uuid],
            sendMail: true,
            mailSubject: 'Your mentor listing was suspended',
        ));

        return $mentor;
    }

    /** Undoes either a rejection or a suspension, restoring the mentor to an approved, active listing. */
    public function reactivateMentor(Admin $actor, string $uuid, Request $request): MentorProfile
    {
        $mentor = $this->findMentorForAdmin($uuid);

        $wasRejected = $mentor->review_status === MentorReviewStatusEnum::REJECTED->value;
        $wasSuspended = $mentor->listing_status === MentorListingStatusEnum::SUSPENDED->value;

        if (! $wasRejected && ! $wasSuspended) {
            throw new ApiException('Only a rejected or suspended mentor can be reactivated.', 422);
        }

        $mentor = $this->mentorProfileRepository->update($mentor, [
            'review_status' => MentorReviewStatusEnum::APPROVED->value,
            'listing_status' => MentorListingStatusEnum::ACTIVE->value,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
            'suspended_by' => null,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::MENTOR_REACTIVATED,
            $request,
            $actor->uuid,
            ['mentor_profile_uuid' => $mentor->uuid],
            $actor->displayName().' reactivated mentor: '.$mentor->user->displayName().'.',
            MentorProfile::class,
            $mentor->uuid,
            ModuleEnums::mentorship,
            200,
        );

        $this->notificationDispatchService->notifyUsersByUuids([$mentor->user->uuid], new GenericDatabaseNotification(
            module: ModuleEnums::mentorship->value,
            event: 'mentor_reactivated',
            title: 'Your mentor listing is active again',
            message: 'You may be matched with mentees again.',
            meta: ['mentor_profile_uuid' => $mentor->uuid],
            sendMail: true,
            mailSubject: 'Your mentor listing is active again',
        ));

        return $mentor;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateReviewsForMentorAdmin(string $mentorUuid, array $filters): LengthAwarePaginator
    {
        $mentor = $this->findMentorForAdmin($mentorUuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->reviewRepository->paginateForMentor($mentor->id, $filters, $perPage);
    }

    /**
     * Mentees with no active match, for the "Unmatched Students" panel of the admin Matching Engine.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateUnmatchedMentees(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->menteeProfileRepository->paginateUnmatched($filters, $perPage);
    }

    /**
     * @return list<array{mentor: MentorProfile, score: int}>
     */
    public function recommendMentorsForMentee(string $menteeUuid, int $limit = 5): array
    {
        $mentee = $this->findMenteeByUuid($menteeUuid);

        return $this->matchingService->recommendationsFor($mentee, $limit);
    }

    /** Admin-initiated equivalent of the auto-matching algorithm, for the Matching Engine's "Approve Match" action. */
    public function matchManually(Admin $actor, string $mentorUuid, string $menteeUuid, Request $request): MentorshipMatch
    {
        $mentee = $this->findMenteeByUuid($menteeUuid);
        $mentor = $this->findMentorForAdmin($mentorUuid);

        if ($this->matchRepository->findActiveForMentee($mentee->id) !== null) {
            throw new ApiException('This mentee already has an active mentor match.', 422);
        }

        if (! $mentor->isAvailableForMatching()) {
            throw new ApiException('This mentor is not available for matching right now.', 422);
        }

        $match = DB::transaction(function () use ($mentor, $mentee): MentorshipMatch {
            $match = $this->matchRepository->create([
                'mentor_profile_id' => $mentor->id,
                'mentee_profile_id' => $mentee->id,
                'status' => MentorshipMatchStatusEnum::ACTIVE->value,
                'matched_by' => MentorshipMatchedByEnum::ADMIN->value,
                'matched_at' => now(),
            ]);

            $this->mentorProfileRepository->incrementMenteeCount($mentor);

            return $match;
        });

        $match->setRelation('mentorProfile', $mentor);
        $match->setRelation('menteeProfile', $mentee);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::MENTORSHIP_MATCHED_MANUALLY,
            $request,
            $actor->uuid,
            ['mentorship_match_uuid' => $match->uuid, 'mentor_profile_uuid' => $mentor->uuid, 'mentee_profile_uuid' => $mentee->uuid],
            $actor->displayName().' matched '.$mentee->user->displayName().' with mentor '.$mentor->user->displayName().'.',
            MentorshipMatch::class,
            $match->uuid,
            ModuleEnums::mentorship,
            201,
        );

        $this->notifyMatched($match);

        return $match;
    }

    /**
     * Finds or opens the direct-message channel between a match's mentor and mentee, so the admin
     * "Chat Mentor or Mentee" screen can reuse the existing Admin/Networking oversight endpoints
     * instead of a parallel messaging system. Admins never send into it; see {@see NetworkingService::listMessagesForAdmin()}.
     */
    public function chatChannelForMatch(string $matchUuid): NetworkingChannel
    {
        $match = $this->matchRepository->findByUuid($matchUuid);

        if (! $match instanceof MentorshipMatch) {
            throw new ApiException('Mentorship match not found.', 404);
        }

        return $this->networkingService->startDirectConversation($match->menteeProfile->user, $match->mentorProfile->user->uuid);
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
