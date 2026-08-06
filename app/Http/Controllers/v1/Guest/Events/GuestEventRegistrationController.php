<?php

namespace App\Http\Controllers\v1\Guest\Events;

use App\Enums\PaymentMethodEnum;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\Events\GuestRegisterForEventRequest;
use App\Http\Resources\Events\EventRegistrationResource;
use App\Responser\JsonResponser;
use App\Services\Events\EventTicketService;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Knuckles\Scribe\Attributes\UrlParam;

/**
 * Guest event registration flow (BRD ENT-04/EVT-07): register for an event without an
 * account. Same registration/payment machinery as the authenticated flow
 * (EventTicketService::register(null, ...)). The only difference is identity comes from
 * `full_name`/`email` in the request instead of a token. There is deliberately no "my
 * tickets" here: a guest has no login to fetch that with later. Use "Verify payment"
 * (Events / Payments group) to confirm the payment, and follow the emailed confirmation for
 * the record of it.
 */
#[Group('Guest Events / Registrations', 'Register for an event without an account (BRD ENT-04, EVT-07).')]
class GuestEventRegistrationController extends Controller
{
    public function __construct(private readonly EventTicketService $eventTicketService) {}

    #[Endpoint('Register for event as guest')]
    #[Unauthenticated]
    #[UrlParam('uuid', 'string', 'Event UUID.', required: true, example: 'e1f2a3b4-c5d6-47e8-98f9-a0b1c2d3e4f5')]
    #[BodyParam('tickets', 'object[]', 'Ticket types and quantities to purchase.', required: true)]
    #[BodyParam('tickets[].ticket_type_uuid', 'string', 'UUID of the ticket type.', required: true, example: 'f2a3b4c5-d6e7-48f9-a9b0-c1d2e3f4a5b6')]
    #[BodyParam('tickets[].quantity', 'int', 'Number of this ticket type to buy.', required: true, example: 1)]
    #[BodyParam('payment_method', 'string', 'Payment method for this charge (ignored for fully free registrations).', required: true, example: 'card', enum: PaymentMethodEnum::class)]
    #[BodyParam('full_name', 'string', 'Attendee\'s full name.', required: true, example: 'Jane Attendee')]
    #[BodyParam('email', 'string', 'Attendee\'s email (used for the payment gateway and the emailed confirmation).', required: true, example: 'jane.attendee@example.com')]
    #[Response(status: 201, content: [
        'error' => false,
        'message' => 'Registration created.',
        'data' => [
            'registration' => ['uuid' => 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6', 'status' => 'pending'],
            'authorization_url' => null,
            'access_code' => null,
            'client_secret' => 'pi_3P.._secret_abc123',
            'publishable_key' => 'pk_test_abc123',
            'reference' => 'TIX_ABCDEFGHIJKLMNOPQRST',
        ],
    ], description: 'Registration created. Default (embedded) mode: Stripe returns client_secret (confirm in-app with Stripe.js/Elements + publishable_key), Paystack returns access_code/publishable_key (pass to Paystack Inline). In hosted mode, the active gateway returns authorization_url instead; for free tickets, all payment fields are null (already completed). See STRIPE_CHECKOUT_MODE / PAYSTACK_CHECKOUT_MODE.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'Registration is closed for this event.',
        'data' => null,
    ], description: 'Event is not open for registration, or a ticket type is sold out/unavailable.')]
    public function store(GuestRegisterForEventRequest $request, string $uuid)
    {
        try {
            $result = $this->eventTicketService->register(null, $uuid, $request->validated(), $request);

            return JsonResponser::send(false, 'Registration created.', [
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
