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
