<?php

namespace App\Http\Controllers\v1\Customer\Mentorship;

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

class MentorController extends Controller
{
    public function __construct(private readonly MentorshipService $mentorshipService) {}

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

    public function index(MentorListRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $paginator = $this->mentorshipService->paginateMyMentors($user, $request->validated());

            return JsonResponser::send(false, 'Mentors retrieved.', $this->paginatedPayload($paginator), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Mentorship\MentorController@index');
        }
    }

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
