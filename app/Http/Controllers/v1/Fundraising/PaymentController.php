<?php

namespace App\Http\Controllers\v1\Fundraising;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Fundraising\PledgePaymentResource;
use App\Http\Resources\Fundraising\PledgeResource;
use App\Responser\JsonResponser;
use App\Services\Fundraising\PledgeService;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Knuckles\Scribe\Attributes\UrlParam;

/**
 * Shared between the customer and guest donate flows: a payment's gateway reference is
 * itself the credential (a random 20-char string only the person who initiated it has), so
 * verifying by reference doesn't need (and guest payments couldn't have) an account.
 */
#[Group('Fundraising / Payments', 'Confirm a pledge payment with the gateway. Public: the reference itself is the credential.')]
class PaymentController extends Controller
{
    public function __construct(private readonly PledgeService $pledgeService) {}

    #[Endpoint('Verify payment')]
    #[Unauthenticated]
    #[UrlParam('reference', 'string', 'Gateway reference returned by "Make a pledge" (customer or guest) or "Pay an installment".', required: true, example: 'PLG_ABCDEFGHIJKLMNOPQRST')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Payment verified.',
        'data' => ['payment' => ['status' => 'successful'], 'pledge' => ['status' => 'on_track']],
    ], description: 'Payment verified with the gateway (successful or failed).')]
    #[Response(status: 404, content: [
        'error' => true,
        'message' => 'Payment not found.',
        'data' => null,
    ], description: 'No payment with that gateway reference.')]
    public function verify(Request $request, string $reference)
    {
        try {
            $result = $this->pledgeService->verifyPayment($reference, $request);

            return JsonResponser::send(false, 'Payment verified.', [
                'payment' => PledgePaymentResource::make($result['payment']),
                'pledge' => PledgeResource::make($result['pledge']),
            ], 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Fundraising\PaymentController@verify');
        }
    }
}
