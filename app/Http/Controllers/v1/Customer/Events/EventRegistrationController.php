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

    /**
     * Register for event
     *
     * Registers the authenticated user for an event. Returns 201 with payment details when a
     * slot is available, or 202 with a waitlist entry when the event is at capacity and its
     * waitlist is enabled.
     *
     * @authenticated
     *
     * @urlParam uuid string required Event UUID. Example: 3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f
     * @bodyParam tickets object[] required At least one ticket line.
     * @bodyParam tickets[].ticket_type_uuid string required UUID of an existing ticket type on this event. Example: 9a1b2c3d-4e5f-6789-abcd-ef0123456789
     * @bodyParam tickets[].quantity integer required Number of tickets requested for this type. Example: 1
     *
     * @response 201 {
     *   "error": false,
     *   "message": "Registration created.",
     *   "data": {
     *     "waitlisted": false,
     *     "registration": {
     *       "uuid": "5c6d7e8f-9a0b-1c2d-3e4f-5a6b7c8d9e0f",
     *       "amount": "5000.00",
     *       "amount_formatted": "₦5,000.00",
     *       "currency": "NGN",
     *       "status": "pending",
     *       "is_guest": false,
     *       "attendee_name": "Jane Doe",
     *       "attendee_email": "jane.doe@example.com",
     *       "completed_at": null
     *     },
     *     "authorization_url": "https://checkout.paystack.com/abc123",
     *     "access_code": "abc123",
     *     "client_secret": null,
     *     "publishable_key": null,
     *     "reference": "EVT-abc123"
     *   }
     * }
     * @response 202 {
     *   "error": false,
     *   "message": "Event is at capacity; you have been added to the waitlist.",
     *   "data": {
     *     "waitlisted": true,
     *     "waitlist_entry_uuid": "2b3c4d5e-6f70-8192-a3b4-c5d6e7f80912",
     *     "waitlist_position": 3
     *   }
     * }
     */
    public function store(RegisterForEventRequest $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $result = $this->eventTicketService->register($user, $uuid, $request->validated(), $request);

            if ($result['waitlisted']) {
                return JsonResponser::send(false, 'Event is at capacity; you have been added to the waitlist.', [
                    'waitlisted' => true,
                    'waitlist_entry_uuid' => $result['waitlist_entry_uuid'],
                    'waitlist_position' => $result['waitlist_position'],
                ], 202);
            }

            return JsonResponser::send(false, 'Registration created.', [
                'waitlisted' => false,
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
