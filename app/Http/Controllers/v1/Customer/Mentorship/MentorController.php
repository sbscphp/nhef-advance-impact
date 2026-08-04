<?php

namespace App\Http\Controllers\v1\Customer\Mentorship;

use App\Enums\MentorshipCommitmentEnum;
use App\Enums\MentorshipFrequencyEnum;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Mentorship\MentorApplicationRequest;
use App\Http\Requests\Customer\Mentorship\MentorListRequest;
use App\Http\Resources\Mentorship\MentorProfileResource;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Mentorship\MentorshipService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group('Customer Mentorship / Mentors', 'Apply to become a mentor, manage your listing, and browse approved mentors. Mentor applications require staff review before appearing in the directory; there is no admin endpoint for that yet, so review happens directly in the database until one exists.')]
class MentorController extends Controller
{
    public function __construct(private readonly MentorshipService $mentorshipService) {}

    #[Endpoint('Apply to become a mentor')]
    #[Authenticated]
    #[BodyParam('current_job_title', 'string', 'Current job title.', required: true, example: 'Product Manager')]
    #[BodyParam('company', 'string', 'Current employer.', required: true, example: 'Acme Ltd')]
    #[BodyParam('years_of_experience', 'int', 'Years of professional experience.', required: true, example: 8)]
    #[BodyParam('professional_summary', 'string', 'Career journey summary.', required: true, example: 'I am a Product Manager with over 8 years of experience across fintech and edtech.')]
    #[BodyParam('major_achievements', 'string', 'Key achievements.', required: false, example: 'Led cross-functional teams and launched customer-focused products.')]
    #[BodyParam('expertise_tags', 'string[]', 'Skills and areas of expertise.', required: true)]
    #[BodyParam('guidance_areas', 'string[]', 'Areas you want to provide guidance in.', required: true)]
    #[BodyParam('max_capacity', 'int', 'Maximum mentees you can mentor at once.', required: true, example: 5)]
    #[BodyParam('available_days', 'string[]', 'Weekdays available, e.g. monday, wednesday.', required: true)]
    #[BodyParam('frequency_of_interaction', 'string', 'How often you want to meet.', required: true, example: 'weekly', enum: MentorshipFrequencyEnum::class)]
    #[BodyParam('program_commitment', 'string', 'How long you are committing to the programme.', required: true, example: 'six_months', enum: MentorshipCommitmentEnum::class)]
    #[BodyParam('linkedin_url', 'string', 'LinkedIn profile URL.', required: false)]
    #[BodyParam('twitter_url', 'string', 'Twitter/X profile URL.', required: false)]
    #[BodyParam('portfolio_url', 'string', 'Portfolio URL.', required: false)]
    #[BodyParam('confirms_experience_requirement', 'boolean', 'Confirms at least 3 years of professional experience.', required: true, example: true)]
    #[BodyParam('confirms_time_commitment', 'boolean', 'Confirms ability to commit at least 2 hours weekly.', required: true, example: true)]
    #[BodyParam('confirms_ethics_agreement', 'boolean', 'Confirms agreement to uphold professional ethics.', required: true, example: true)]
    #[Response(status: 201, content: [
        'error' => false,
        'message' => 'Mentor application submitted.',
        'data' => ['uuid' => 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6', 'review_status' => 'pending'],
    ], description: 'Application created; awaiting staff review.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'You have already applied to be a mentor.',
        'data' => null,
    ], description: 'A mentor profile already exists for this account.')]
    public function apply(MentorApplicationRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $mentor = $this->mentorshipService->applyAsMentor($user, $request->validated(), $request);

            return JsonResponser::send(false, 'Mentor application submitted.', MentorProfileResource::make($mentor), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Mentorship\MentorController@apply');
        }
    }

    #[Endpoint('My mentor profile')]
    #[Authenticated]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Mentor profile retrieved.',
        'data' => ['uuid' => 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6', 'review_status' => 'approved', 'listing_status' => 'active'],
    ], description: 'Your mentor profile.')]
    #[Response(status: 404, content: [
        'error' => true,
        'message' => 'You have not applied to be a mentor.',
        'data' => null,
    ], description: 'No mentor profile exists for this account.')]
    public function me(Request $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $mentor = $this->mentorshipService->findMyMentorProfile($user);

            return JsonResponser::send(false, 'Mentor profile retrieved.', MentorProfileResource::make($mentor), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Mentorship\MentorController@me');
        }
    }

    #[Endpoint('Pause mentor listing')]
    #[Authenticated]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Mentor listing paused.',
        'data' => ['listing_status' => 'paused'],
    ], description: 'You will no longer receive new matches until you resume.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'Your mentor listing is already paused.',
        'data' => null,
    ], description: 'Listing was not active.')]
    public function pause(Request $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $mentor = $this->mentorshipService->pauseListing($user, $request);

            return JsonResponser::send(false, 'Mentor listing paused.', MentorProfileResource::make($mentor), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Mentorship\MentorController@pause');
        }
    }

    #[Endpoint('Resume mentor listing')]
    #[Authenticated]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Mentor listing resumed.',
        'data' => ['listing_status' => 'active'],
    ], description: 'You can receive new matches again.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'Your mentor listing is already active.',
        'data' => null,
    ], description: 'Listing was not paused.')]
    public function resume(Request $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $mentor = $this->mentorshipService->resumeListing($user, $request);

            return JsonResponser::send(false, 'Mentor listing resumed.', MentorProfileResource::make($mentor), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Mentorship\MentorController@resume');
        }
    }

    #[Endpoint('Browse mentors')]
    #[Authenticated]
    #[QueryParam('search', 'string', 'Filter by mentor name (partial match).', required: false, example: 'Ethan')]
    #[QueryParam('start_date', 'date', 'Only mentors approved/created on/after this date.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'date', 'Only mentors approved/created on/before this date.', required: false, example: '2026-01-31')]
    #[QueryParam('sort_by', 'string', 'Order arrangement field: "name" (mentor name).', required: false, example: 'name')]
    #[QueryParam('sort_direction', 'string', 'Order arrangement direction: asc or desc.', required: false, example: 'asc')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Results per page (max 100).', required: false, example: 15)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Mentors retrieved.',
        'data' => ['current_page' => 1, 'data' => [], 'per_page' => 15, 'total' => 0],
    ], description: 'Paginated list of approved mentors.')]
    public function index(MentorListRequest $request)
    {
        try {
            $paginator = $this->mentorshipService->paginateMentors($request->validated());

            return JsonResponser::send(false, 'Mentors retrieved.', $this->paginatedPayload($paginator), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Mentorship\MentorController@index');
        }
    }

    #[Endpoint('View mentor')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Mentor profile UUID.', required: true, example: 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Mentor retrieved.',
        'data' => ['uuid' => 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6'],
    ], description: 'Mentor found.')]
    #[Response(status: 404, content: [
        'error' => true,
        'message' => 'Mentor not found.',
        'data' => null,
    ], description: 'No approved mentor matches the given UUID.')]
    public function show(Request $request, string $uuid)
    {
        try {
            $mentor = $this->mentorshipService->findMentorByUuid($uuid);

            return JsonResponser::send(false, 'Mentor retrieved.', MentorProfileResource::make($mentor), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Mentorship\MentorController@show');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator): array
    {
        $payload = $paginator->toArray();
        $payload['data'] = MentorProfileResource::collection($paginator)->resolve();

        return $payload;
    }

    private function requireCustomer(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403, 'Forbidden.');
        }

        return $user;
    }
}
