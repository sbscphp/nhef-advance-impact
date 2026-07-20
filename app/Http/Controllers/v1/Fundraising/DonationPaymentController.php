<?php

namespace App\Http\Controllers\v1\Fundraising;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Fundraising\DonationPaymentResource;
use App\Http\Resources\Fundraising\DonationResource;
use App\Responser\JsonResponser;
use App\Services\Fundraising\DonationService;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Knuckles\Scribe\Attributes\UrlParam;

/**
 * Shared between the customer and guest donate flows — a payment's gateway reference is
 * itself the credential (a random 20-char string only the person who initiated it has), so
 * verifying by reference doesn't need — and guest payments couldn't have — an account.
 */
#[Group('Fundraising / Payments', 'Confirm a donation payment with the gateway. Public — the reference itself is the credential.')]
class DonationPaymentController extends Controller
{
    public function __construct(private readonly DonationService $donationService) {}

    /**
     * Verify payment
     *
     * Confirms a payment's status with the gateway. Safe to call after redirect back from
     * checkout even if the webhook already confirmed it (idempotent).
     */
    #[Endpoint('Verify payment')]
    #[Unauthenticated]
    #[UrlParam('reference', 'string', 'Gateway reference returned by "Make a donation" (customer or guest) or "Charge next cycle".', required: true, example: 'DON_ABCDEFGHIJKLMNOPQRST')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Payment verified.',
        'data' => ['payment' => ['status' => 'successful'], 'donation' => ['status' => 'completed']],
    ], description: 'Payment verified with the gateway (successful or failed).')]
    #[Response(status: 404, content: [
        'error' => true,
        'message' => 'Payment not found.',
        'data' => null,
    ], description: 'No payment with that gateway reference.')]
    public function verify(Request $request, string $reference)
    {
        try {
            $result = $this->donationService->verifyPayment($reference, $request);

            return JsonResponser::send(false, 'Payment verified.', [
                'payment' => DonationPaymentResource::make($result['payment']),
                'donation' => DonationResource::make($result['donation']),
            ], 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Fundraising\DonationPaymentController@verify');
        }
    }
}
