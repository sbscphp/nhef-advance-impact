<?php

namespace App\Http\Controllers\v1\Fundraising;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Fundraising\PledgePaymentResource;
use App\Http\Resources\Fundraising\PledgeResource;
use App\Responser\JsonResponser;
use App\Services\Fundraising\PledgeService;
use Illuminate\Http\Request;

/**
 * Shared between the customer and guest donate flows: a payment's gateway reference is
 * itself the credential, so verifying by reference needs no account.
 */
class PaymentController extends Controller
{
    public function __construct(private readonly PledgeService $pledgeService) {}

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
