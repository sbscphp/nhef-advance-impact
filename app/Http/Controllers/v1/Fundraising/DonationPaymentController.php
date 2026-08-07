<?php

namespace App\Http\Controllers\v1\Fundraising;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Fundraising\DonationPaymentResource;
use App\Http\Resources\Fundraising\DonationResource;
use App\Responser\JsonResponser;
use App\Services\Fundraising\DonationService;
use Illuminate\Http\Request;

/**
 * Shared between the customer and guest donate flows: a payment's gateway reference is
 * itself the credential, so verifying by reference needs no account.
 */
class DonationPaymentController extends Controller
{
    public function __construct(private readonly DonationService $donationService) {}

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
