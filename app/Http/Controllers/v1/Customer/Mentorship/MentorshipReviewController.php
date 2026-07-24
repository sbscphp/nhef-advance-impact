<?php

namespace App\Http\Controllers\v1\Customer\Mentorship;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Mentorship\MentorshipReviewListRequest;
use App\Http\Requests\Customer\Mentorship\MentorshipReviewRequest;
use App\Http\Resources\Mentorship\MentorshipReviewResource;
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

#[Group('Customer Mentorship / Reviews', "A mentee's rating of their mentor after being matched. One review per match, mentee to mentor only.")]
class MentorshipReviewController extends Controller
{
    public function __construct(private readonly MentorshipService $mentorshipService) {}

    #[Endpoint('Review a mentor')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Mentor profile UUID.', required: true, example: 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6')]
    #[BodyParam('quality_rating', 'int', 'Quality rating, 1-5.', required: true, example: 5)]
    #[BodyParam('communication_rating', 'int', 'Communication rating, 1-5.', required: true, example: 5)]
    #[BodyParam('responsiveness_rating', 'int', 'Responsiveness rating, 1-5.', required: true, example: 4)]
    #[BodyParam('professionalism_rating', 'int', 'Professionalism rating, 1-5.', required: true, example: 5)]
    #[BodyParam('description', 'string', 'How the mentor impacted you.', required: true, example: 'My mentor provided invaluable career guidance.')]
    #[Response(status: 201, content: [
        'error' => false,
        'message' => 'Review submitted.',
        'data' => ['uuid' => 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8', 'average_rating' => 4.8],
    ], description: 'Review created.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'You do not have an active mentorship with this mentor.',
        'data' => null,
    ], description: 'No active match with this mentor, or a review already exists for it.')]
    public function store(MentorshipReviewRequest $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $review = $this->mentorshipService->submitReview($user, $uuid, $request->validated(), $request);

            return JsonResponser::send(false, 'Review submitted.', MentorshipReviewResource::make($review), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Mentorship\MentorshipReviewController@store');
        }
    }

    #[Endpoint('List reviews for a mentor')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Mentor profile UUID.', required: true, example: 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Results per page (max 100).', required: false, example: 15)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Reviews retrieved.',
        'data' => ['current_page' => 1, 'data' => [], 'per_page' => 15, 'total' => 0],
    ], description: 'Paginated list of reviews for the mentor.')]
    public function index(MentorshipReviewListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->mentorshipService->paginateReviewsForMentor($uuid, $request->validated());

            return JsonResponser::send(false, 'Reviews retrieved.', $this->paginatedPayload($paginator), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Mentorship\MentorshipReviewController@index');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator): array
    {
        $payload = $paginator->toArray();
        $payload['data'] = MentorshipReviewResource::collection($paginator)->resolve();

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
