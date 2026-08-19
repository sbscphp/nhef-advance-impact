<?php

namespace App\Http\Controllers\v1\Guest\Events;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\Events\GuestRegisterForEventRequest;
use App\Http\Resources\Events\EventRegistrationResource;
use App\Responser\JsonResponser;
use App\Services\Events\EventTicketService;

/**
 * Guest event registration flow (BRD ENT-04/EVT-07): register without an account, using the
 * same registration/payment machinery as the authenticated flow (identity comes from
 * `full_name`/`email` instead of a token). No "my tickets" here since a guest has no login;
 * use "Verify payment" and the emailed confirmation instead.
 */
class GuestEventRegistrationController extends Controller
{
    public function __construct(private readonly EventTicketService $eventTicketService) {}

    /**
     * Register for event (guest)
     *
     * Registers a guest (no account) for an event. Returns 201 with payment details when a
     * slot is available, or 202 with a waitlist entry when the event is at capacity and its
     * waitlist is enabled.
     *
     * @unauthenticated
     *
     * @urlParam uuid string required Event UUID. Example: 3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f
     * @bodyParam tickets object[] required At least one ticket line.
     * @bodyParam tickets[].ticket_type_uuid string required UUID of an existing ticket type on this event. Example: 9a1b2c3d-4e5f-6789-abcd-ef0123456789
     * @bodyParam tickets[].quantity integer required Number of tickets requested for this type. Example: 1
     * @bodyParam full_name string required Guest's full name. Example: John Smith
     * @bodyParam email string required Guest's email address. Example: john.smith@example.com
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
     *       "is_guest": true,
     *       "attendee_name": "John Smith",
     *       "attendee_email": "john.smith@example.com",
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
    public function store(GuestRegisterForEventRequest $request, string $uuid)
    {
        try {
            $result = $this->eventTicketService->register(null, $uuid, $request->validated(), $request);

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
            return GeneralHelper::handleControllerThrowable($th, 'Guest\Events\GuestEventRegistrationController@store');
        }
    }
}
