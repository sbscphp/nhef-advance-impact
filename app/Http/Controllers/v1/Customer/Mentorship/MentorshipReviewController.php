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

class MentorshipReviewController extends Controller
{
    public function __construct(private readonly MentorshipService $mentorshipService) {}

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
