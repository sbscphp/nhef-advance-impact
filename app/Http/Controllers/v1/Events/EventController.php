<?php

namespace App\Http\Controllers\v1\Events;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\EventListRequest;
use App\Http\Resources\Events\EventResource;
use App\Responser\JsonResponser;
use App\Services\Events\EventTicketService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Knuckles\Scribe\Attributes\UrlParam;

/**
 * Public: events are browsed by both logged-in alumni and guests (see
 * App\Http\Controllers\v1\Guest\Events\GuestEventRegistrationController), so there's nothing
 * here that needs an account.
 */
#[Group('Events', 'Browse published events available for ticket registration (BRD EVT-01, EVT-02). Public: no account required.')]
class EventController extends Controller
{
    public function __construct(private readonly EventTicketService $eventTicketService) {}

    #[Endpoint('List events')]
    #[Unauthenticated]
    #[QueryParam('search', 'string', 'Filter by title (partial match).', required: false, example: 'Coding Bootcamp')]
    #[QueryParam('start_date', 'date', 'Only events created on/after this date.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'date', 'Only events created on/before this date.', required: false, example: '2026-01-31')]
    #[QueryParam('sort_by', 'string', 'Order arrangement field: "name" (title). Defaults to soonest-first by start date.', required: false, example: 'name')]
    #[QueryParam('sort_direction', 'string', 'Order arrangement direction: asc or desc.', required: false, example: 'asc')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Results per page (max 100).', required: false, example: 15)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Events retrieved.',
        'data' => [
            'current_page' => 1,
            'data' => [],
            'per_page' => 15,
            'total' => 0,
        ],
    ], description: 'Paginated list of published events.')]
    public function index(EventListRequest $request)
    {
        try {
            $paginator = $this->eventTicketService->paginateEvents($request->validated());

            return JsonResponser::send(false, 'Events retrieved.', $this->paginatedPayload($paginator), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Events\EventController@index');
        }
    }

    #[Endpoint('View event')]
    #[Unauthenticated]
    #[UrlParam('uuid', 'string', 'Event UUID.', required: true, example: 'e1f2a3b4-c5d6-47e8-98f9-a0b1c2d3e4f5')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Event retrieved.',
        'data' => ['uuid' => 'e1f2a3b4-c5d6-47e8-98f9-a0b1c2d3e4f5', 'title' => 'Summer Coding Bootcamp', 'timeline_status' => 'upcoming'],
    ], description: 'Event found.')]
    #[Response(status: 404, content: [
        'error' => true,
        'message' => 'Event not found.',
        'data' => null,
    ], description: 'No published event matches the given UUID.')]
    public function show(Request $request, string $uuid)
    {
        try {
            $event = $this->eventTicketService->findEventByUuid($uuid);

            return JsonResponser::send(false, 'Event retrieved.', EventResource::make($event), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Events\EventController@show');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator): array
    {
        $payload = $paginator->toArray();
        $payload['data'] = EventResource::collection($paginator)->resolve();

        return $payload;
    }
}
