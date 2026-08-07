<?php

namespace App\Http\Controllers\v1\Customer\Mentorship;

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

class MenteeController extends Controller
{
    public function __construct(private readonly MentorshipService $mentorshipService) {}

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

    public function index(MenteeListRequest $request)
    {
        try {
            $paginator = $this->mentorshipService->paginateMentees($request->validated());

            return JsonResponser::send(false, 'Mentees retrieved.', $this->paginatedPayload($paginator), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Mentorship\MenteeController@index');
        }
    }

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
