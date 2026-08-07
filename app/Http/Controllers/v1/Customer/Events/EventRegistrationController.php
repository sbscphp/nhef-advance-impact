<?php

namespace App\Http\Controllers\v1\Customer\Events;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Events\EventRegistrationListRequest;
use App\Http\Requests\Customer\Events\RegisterForEventRequest;
use App\Http\Resources\Events\EventRegistrationResource;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Events\EventTicketService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class EventRegistrationController extends Controller
{
    public function __construct(private readonly EventTicketService $eventTicketService) {}

    public function store(RegisterForEventRequest $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $result = $this->eventTicketService->register($user, $uuid, $request->validated(), $request);

            return JsonResponser::send(false, 'Registration created.', [
                'registration' => EventRegistrationResource::make($result['registration']),
                'authorization_url' => $result['authorization_url'],
                'access_code' => $result['access_code'],
                'client_secret' => $result['client_secret'],
                'publishable_key' => $result['publishable_key'],
                'reference' => $result['reference'],
            ], 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Events\EventRegistrationController@store');
        }
    }

    public function index(EventRegistrationListRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $paginator = $this->eventTicketService->paginateRegistrationsForUser($user, $request->validated());

            return JsonResponser::send(false, 'Registrations retrieved.', $this->paginatedPayload($paginator), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Events\EventRegistrationController@index');
        }
    }

    public function show(Request $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $registration = $this->eventTicketService->findRegistrationForUser($user, $uuid);

            return JsonResponser::send(false, 'Registration retrieved.', EventRegistrationResource::make($registration), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Events\EventRegistrationController@show');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator): array
    {
        $payload = $paginator->toArray();
        $payload['data'] = EventRegistrationResource::collection($paginator)->resolve();

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
