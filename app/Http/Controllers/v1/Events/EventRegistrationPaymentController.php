<?php

namespace App\Http\Controllers\v1\Events;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Events\EventRegistrationPaymentResource;
use App\Http\Resources\Events\EventRegistrationResource;
use App\Responser\JsonResponser;
use App\Services\Events\EventTicketService;
use Illuminate\Http\Request;

/**
 * Shared between the customer and guest registration flows: a payment's gateway reference is
 * itself the credential, so verifying by reference needs no account.
 */
class EventRegistrationPaymentController extends Controller
{
    public function __construct(private readonly EventTicketService $eventTicketService) {}

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
