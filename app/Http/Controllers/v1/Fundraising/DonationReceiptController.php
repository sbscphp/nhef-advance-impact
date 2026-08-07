<?php

namespace App\Http\Controllers\v1\Fundraising;

use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Models\DonationPayment;
use App\Services\Fundraising\DonationReceiptService;
use Illuminate\Http\Request;

/**
 * Streams a donation receipt PDF from a signed URL. No Bearer token needed: it's opened
 * directly from an emailed link (or a freshly fetched one, see DonationPaymentResource::receipt_url),
 * and the `signed` middleware validates the URL's signature instead.
 */
class DonationReceiptController extends Controller
{
    public function __construct(private readonly DonationReceiptService $receiptService) {}

    public function download(Request $request, string $uuid)
    {
        try {
            $payment = DonationPayment::query()->with(['donation.campaign'])->where('uuid', $uuid)->first();

            if (! $payment instanceof DonationPayment) {
                throw new ApiException('Payment not found.', 404);
            }

            return $this->receiptService->render($payment);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Fundraising\DonationReceiptController@download');
        }
    }
}
