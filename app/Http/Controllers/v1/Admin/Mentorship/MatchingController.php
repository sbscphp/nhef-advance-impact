<?php

namespace App\Http\Controllers\v1\Admin\Mentorship;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Mentorship\ManualMatchRequest;
use App\Http\Requests\Admin\Mentorship\UnmatchedMenteeListRequest;
use App\Http\Resources\Mentorship\MenteeProfileResource;
use App\Http\Resources\Mentorship\MentorProfileResource;
use App\Http\Resources\Mentorship\MentorshipMatchResource;
use App\Http\Resources\Networking\NetworkingChannelResource;
use App\Models\Admin;
use App\Responser\JsonResponser;
use App\Services\Mentorship\MentorshipService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class MatchingController extends Controller
{
    public function __construct(private readonly MentorshipService $mentorshipService) {}

    public function unmatchedMentees(UnmatchedMenteeListRequest $request)
    {
        try {
            $paginator = $this->mentorshipService->paginateUnmatchedMentees($request->validated());

            return JsonResponser::send(false, 'Unmatched mentees retrieved.', $this->paginatedPayload($paginator));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Mentorship\MatchingController@unmatchedMentees');
        }
    }

    public function recommendations(Request $request, string $uuid)
    {
        try {
            $limit = max(1, min((int) $request->query('limit', 5), 20));
            $recommendations = $this->mentorshipService->recommendMentorsForMentee($uuid, $limit);

            $payload = array_map(fn (array $row): array => [
                'mentor' => MentorProfileResource::make($row['mentor'])->resolve(),
                'match_score' => $row['score'],
            ], $recommendations);

            return JsonResponser::send(false, 'Recommended mentors retrieved.', $payload);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Mentorship\MatchingController@recommendations');
        }
    }

    public function store(ManualMatchRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $validated = $request->validated();
            $match = $this->mentorshipService->matchManually($admin, $validated['mentor_uuid'], $validated['mentee_uuid'], $request);

            return JsonResponser::send(false, 'Mentor and mentee matched.', MentorshipMatchResource::make($match)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Mentorship\MatchingController@store');
        }
    }

    public function chat(string $uuid)
    {
        try {
            $channel = $this->mentorshipService->chatChannelForMatch($uuid);

            return JsonResponser::send(false, 'Conversation retrieved.', NetworkingChannelResource::make($channel)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Mentorship\MatchingController@chat');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator): array
    {
        $payload = $paginator->toArray();
        /** @var AnonymousResourceCollection $resource */
        $resource = MenteeProfileResource::collection($paginator);
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
