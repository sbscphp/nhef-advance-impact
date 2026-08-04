<?php

namespace App\Http\Controllers\v1\Customer\Mentorship;

use App\Enums\MentorshipFrequencyEnum;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Mentorship\MenteeApplicationRequest;
use App\Http\Requests\Customer\Mentorship\MenteeListRequest;
use App\Http\Resources\Mentorship\MenteeProfileResource;
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

#[Group('Customer Mentorship / Mentees', 'Apply to be mentored and browse mentees. Submitting an application is auto-approved and immediately attempts to auto-match you with an approved mentor who shares at least one interest area.')]
class MenteeController extends Controller
{
    public function __construct(private readonly MentorshipService $mentorshipService) {}

    #[Endpoint('Apply to be mentored')]
    #[Authenticated]
    #[BodyParam('interest_areas', 'string[]', "Areas you want a mentor's guidance in.", required: true)]
    #[BodyParam('skills', 'string[]', 'Your current skills.', required: true)]
    #[BodyParam('professional_summary', 'string', 'Career journey summary.', required: true)]
    #[BodyParam('why_mentor_needed', 'string', 'Why you want a mentor.', required: true)]
    #[BodyParam('available_days', 'string[]', 'Weekdays available, e.g. monday, wednesday.', required: true)]
    #[BodyParam('frequency_of_interaction', 'string', 'How often you want to meet.', required: true, example: 'weekly', enum: MentorshipFrequencyEnum::class)]
    #[BodyParam('linkedin_url', 'string', 'LinkedIn profile URL.', required: false)]
    #[BodyParam('twitter_url', 'string', 'Twitter/X profile URL.', required: false)]
    #[BodyParam('portfolio_url', 'string', 'Portfolio URL.', required: false)]
    #[BodyParam('confirms_information_accuracy', 'boolean', 'Certifies the information provided is accurate.', required: true, example: true)]
    #[BodyParam('confirms_matching_understanding', 'boolean', 'Confirms understanding that matching is based on compatibility and mentor availability.', required: true, example: true)]
    #[BodyParam('confirms_active_participation', 'boolean', 'Agrees to actively participate in the mentorship programme.', required: true, example: true)]
    #[Response(status: 201, content: [
        'error' => false,
        'message' => 'Mentee application submitted.',
        'data' => [
            'mentee' => ['uuid' => 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7'],
            'matched' => true,
        ],
    ], description: '"matched" is true if an available mentor shared an interest area and you were paired immediately; false means you stay listed until a match becomes available.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'You have already applied to be a mentee.',
        'data' => null,
    ], description: 'A mentee profile already exists for this account.')]
    public function apply(MenteeApplicationRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $result = $this->mentorshipService->applyAsMentee($user, $request->validated(), $request);

            return JsonResponser::send(false, 'Mentee application submitted.', [
                'mentee' => MenteeProfileResource::make($result['mentee']),
                'matched' => $result['match'] !== null,
            ], 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Mentorship\MenteeController@apply');
        }
    }

    #[Endpoint('My mentee profile')]
    #[Authenticated]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Mentee profile retrieved.',
        'data' => ['uuid' => 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7'],
    ], description: 'Your mentee profile, including your active match if you have one.')]
    #[Response(status: 404, content: [
        'error' => true,
        'message' => 'You have not applied to be a mentee.',
        'data' => null,
    ], description: 'No mentee profile exists for this account.')]
    public function me(Request $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $mentee = $this->mentorshipService->findMyMenteeProfile($user);

            return JsonResponser::send(false, 'Mentee profile retrieved.', MenteeProfileResource::make($mentee), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Mentorship\MenteeController@me');
        }
    }

    #[Endpoint('Browse mentees')]
    #[Authenticated]
    #[QueryParam('search', 'string', 'Filter by mentee name (partial match).', required: false, example: 'Nina')]
    #[QueryParam('start_date', 'date', 'Only mentees created on/after this date.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'date', 'Only mentees created on/before this date.', required: false, example: '2026-01-31')]
    #[QueryParam('sort_by', 'string', 'Order arrangement field: "name" (mentee name).', required: false, example: 'name')]
    #[QueryParam('sort_direction', 'string', 'Order arrangement direction: asc or desc.', required: false, example: 'asc')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Results per page (max 100).', required: false, example: 15)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Mentees retrieved.',
        'data' => ['current_page' => 1, 'data' => [], 'per_page' => 15, 'total' => 0],
    ], description: 'Paginated list of mentees.')]
    public function index(MenteeListRequest $request)
    {
        try {
            $paginator = $this->mentorshipService->paginateMentees($request->validated());

            return JsonResponser::send(false, 'Mentees retrieved.', $this->paginatedPayload($paginator), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Mentorship\MenteeController@index');
        }
    }

    #[Endpoint('View mentee')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Mentee profile UUID.', required: true, example: 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Mentee retrieved.',
        'data' => ['uuid' => 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7'],
    ], description: 'Mentee found.')]
    #[Response(status: 404, content: [
        'error' => true,
        'message' => 'Mentee not found.',
        'data' => null,
    ], description: 'No mentee matches the given UUID.')]
    public function show(Request $request, string $uuid)
    {
        try {
            $mentee = $this->mentorshipService->findMenteeByUuid($uuid);

            return JsonResponser::send(false, 'Mentee retrieved.', MenteeProfileResource::make($mentee), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Mentorship\MenteeController@show');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator): array
    {
        $payload = $paginator->toArray();
        $payload['data'] = MenteeProfileResource::collection($paginator)->resolve();

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
