<?php

namespace App\Http\Controllers\v1\Admin\Mentorship;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Mentorship\MenteeListRequest;
use App\Http\Resources\Mentorship\MenteeProfileResource;
use App\Responser\JsonResponser;
use App\Services\Mentorship\MentorshipService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class MenteeController extends Controller
{
    public function __construct(private readonly MentorshipService $mentorshipService) {}

    public function index(MenteeListRequest $request)
    {
        try {
            $paginator = $this->mentorshipService->paginateMentees($request->validated());

            return JsonResponser::send(false, 'Mentees retrieved.', $this->paginatedPayload($paginator));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Mentorship\MenteeController@index');
        }
    }

    public function show(string $uuid)
    {
        try {
            $mentee = $this->mentorshipService->findMenteeByUuid($uuid);

            return JsonResponser::send(false, 'Mentee retrieved.', MenteeProfileResource::make($mentee)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Mentorship\MenteeController@show');
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
}
