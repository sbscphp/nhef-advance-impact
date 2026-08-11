<?php

namespace App\Http\Controllers\v1\Admin\Networking;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Networking\AlumniSearchRequest;
use App\Http\Resources\Networking\NetworkingChannelMemberResource;
use App\Responser\JsonResponser;
use App\Services\Networking\NetworkingService;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;

/** Backs the "Search & Select Alumni" step of Add New Community/Forum. */
#[Group('Admin / Networking / Alumni Search', 'Search alumni by name, email, or organisation when picking members for a Community/Forum. Requires the `networking.read` permission.')]
class AlumniSearchController extends Controller
{
    public function __construct(private readonly NetworkingService $networkingService) {}

    #[Endpoint('Search alumni')]
    #[Authenticated]
    #[QueryParam('search', 'string', 'Matches name, email, or organisation.', required: false, example: 'Adeola')]
    #[QueryParam('sort_direction', 'string', 'asc or desc; sorts by name (firstname, lastname).', required: false, example: 'asc')]
    #[QueryParam('period', 'string', 'Filters by account creation date. One of: 1day, 3days, 7days, 14days, 30days, 3months, 6months, 1year, lastyear, custom.', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Start date; required when period=custom.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'End date; required when period=custom.', required: false, example: '2026-08-01')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Results per page (max 100).', required: false, example: 15)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Alumni retrieved.',
        'data' => ['current_page' => 1, 'data' => [], 'per_page' => 15, 'total' => 0],
    ], description: 'Paginated alumni list for the member picker.')]
    public function index(AlumniSearchRequest $request)
    {
        try {
            $paginator = $this->networkingService->searchAlumni($request->validated());

            $payload = $paginator->toArray();
            $payload['data'] = NetworkingChannelMemberResource::collection($paginator)->resolve();

            return JsonResponser::send(false, 'Alumni retrieved.', $payload);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Networking\AlumniSearchController@index');
        }
    }
}
