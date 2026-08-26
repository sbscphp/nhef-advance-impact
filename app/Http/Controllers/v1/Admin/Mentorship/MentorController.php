<?php

namespace App\Http\Controllers\v1\Admin\Mentorship;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Mentorship\MentorListRequest;
use App\Http\Requests\Admin\Mentorship\MentorshipReviewListRequest;
use App\Http\Requests\Admin\Mentorship\RejectMentorApplicationRequest;
use App\Http\Requests\Admin\Mentorship\SuspendMentorRequest;
use App\Http\Resources\Mentorship\MentorProfileResource;
use App\Http\Resources\Mentorship\MentorshipReviewResource;
use App\Models\Admin;
use App\Responser\JsonResponser;
use App\Services\Mentorship\MentorshipService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class MentorController extends Controller
{
    public function __construct(private readonly MentorshipService $mentorshipService) {}

    public function index(MentorListRequest $request)
    {
        try {
            $paginator = $this->mentorshipService->paginateMentorsForAdmin($request->validated());

            return JsonResponser::send(false, 'Mentors retrieved.', $this->paginatedPayload($paginator, MentorProfileResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Mentorship\MentorController@index');
        }
    }

    public function show(string $uuid)
    {
        try {
            $mentor = $this->mentorshipService->findMentorForAdmin($uuid);

            return JsonResponser::send(false, 'Mentor retrieved.', MentorProfileResource::make($mentor)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Mentorship\MentorController@show');
        }
    }

    public function approve(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $mentor = $this->mentorshipService->approveMentorApplication($admin, $uuid, $request);

            return JsonResponser::send(false, 'Mentor application approved.', MentorProfileResource::make($mentor)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Mentorship\MentorController@approve');
        }
    }

    public function reject(RejectMentorApplicationRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $mentor = $this->mentorshipService->rejectMentorApplication($admin, $uuid, $request->validated()['reason'], $request);

            return JsonResponser::send(false, 'Mentor application rejected.', MentorProfileResource::make($mentor)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Mentorship\MentorController@reject');
        }
    }

    public function suspend(SuspendMentorRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $mentor = $this->mentorshipService->suspendMentor($admin, $uuid, $request->validated()['reason'], $request);

            return JsonResponser::send(false, 'Mentor suspended.', MentorProfileResource::make($mentor)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Mentorship\MentorController@suspend');
        }
    }

    public function reactivate(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $mentor = $this->mentorshipService->reactivateMentor($admin, $uuid, $request);

            return JsonResponser::send(false, 'Mentor reactivated.', MentorProfileResource::make($mentor)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Mentorship\MentorController@reactivate');
        }
    }

    public function reviews(MentorshipReviewListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->mentorshipService->paginateReviewsForMentorAdmin($uuid, $request->validated());

            return JsonResponser::send(false, 'Reviews retrieved.', $this->paginatedPayload($paginator, MentorshipReviewResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Mentorship\MentorController@reviews');
        }
    }

    /**
     * @param  class-string<JsonResource>  $resourceClass
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator, string $resourceClass): array
    {
        $payload = $paginator->toArray();
        /** @var AnonymousResourceCollection $resource */
        $resource = $resourceClass::collection($paginator);
        $payload['data'] = $resource->resolve();

        return $payload;
    }

    private function requireAdmin(Request $request): Admin
    {
        $admin = $request->user();
        if (! $admin instanceof Admin) {
            abort(403, 'Forbidden.');
        }

        return $admin;
    }
}
