<?php

namespace App\Http\Controllers\v1\Events;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\EventListRequest;
use App\Http\Resources\Events\EventResource;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Events\EventTicketService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Public: events are browsed by both logged-in alumni and guests, so nothing here needs an
 * account. When a donor is signed in (routes/public.php's auth.attempt middleware resolves
 * them without requiring auth), the listing scopes to their own institution.
 */
class EventController extends Controller
{
    public function __construct(private readonly EventTicketService $eventTicketService) {}

    public function index(EventListRequest $request)
    {
        try {
            $viewer = $request->user() instanceof User ? $request->user() : null;
            $paginator = $this->eventTicketService->paginateEvents($request->validated(), $viewer);

            return JsonResponser::send(false, 'Events retrieved.', $this->paginatedPayload($paginator), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Events\EventController@index');
        }
    }

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
