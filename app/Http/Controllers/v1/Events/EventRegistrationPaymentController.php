<?php

namespace App\Http\Controllers\v1\Events;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Events\EventRegistrationPaymentResource;
use App\Http\Resources\Events\EventRegistrationResource;
use App\Responser\JsonResponser;
use App\Services\Events\EventTicketService;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Knuckles\Scribe\Attributes\UrlParam;

/**
 * Shared between the customer and guest registration flows — a payment's gateway reference is
 * itself the credential (a random 20-char string only the person who initiated it has), so
 * verifying by reference doesn't need — and guest payments couldn't have — an account.
 */
#[Group('Events / Payments', 'Confirm an event ticket payment with the gateway. Public — the reference itself is the credential.')]
class EventRegistrationPaymentController extends Controller
{
    public function __construct(private readonly EventTicketService $eventTicketService) {}

    /**
     * Verify payment
     *
     * Confirms a payment's status with the gateway. Safe to call after redirect back from
     * checkout even if the webhook already confirmed it (idempotent).
     */
    #[Endpoint('Verify payment')]
    #[Unauthenticated]
    #[UrlParam('reference', 'string', 'Gateway reference returned by "Register for event" (customer or guest).', required: true, example: 'TIX_ABCDEFGHIJKLMNOPQRST')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Payment verified.',
        'data' => ['payment' => ['status' => 'successful'], 'registration' => ['status' => 'completed']],
    ], description: 'Payment verified with the gateway (successful or failed).')]
    #[Response(status: 404, content: [
        'error' => true,
        'message' => 'Payment not found.',
        'data' => null,
    ], description: 'No payment with that gateway reference.')]
    public function verify(Request $request, string $reference)
    {
        try {
            $result = $this->eventTicketService->verifyPayment($reference, $request);

            return JsonResponser::send(false, 'Payment verified.', [
                'payment' => EventRegistrationPaymentResource::make($result['payment']),
                'registration' => EventRegistrationResource::make($result['registration']),
            ], 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Events\EventRegistrationPaymentController@verify');
        }
    }
}
