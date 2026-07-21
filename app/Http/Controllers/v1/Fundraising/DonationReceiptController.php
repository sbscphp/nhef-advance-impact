<?php

namespace App\Http\Controllers\v1\Fundraising;

use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Models\DonationPayment;
use App\Services\Fundraising\DonationReceiptService;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Knuckles\Scribe\Attributes\UrlParam;

/**
 * Streams a donation receipt PDF from a signed URL — no Bearer token needed, since this is
 * meant to be opened directly from an emailed receipt link (or the "Download Receipt" button
 * in the app, which just fetches a fresh signed URL from DonationPaymentResource::receipt_url).
 * The `signed` middleware validates the URL's signature; there's no separate stored token.
 */
#[Group('Fundraising / Receipts', 'Download a donation receipt PDF via a signed URL. Public — the signature itself is the credential.')]
class DonationReceiptController extends Controller
{
    public function __construct(private readonly DonationReceiptService $receiptService) {}

    /**
     * Download donation receipt
     */
    #[Endpoint('Download donation receipt')]
    #[Unauthenticated]
    #[UrlParam('uuid', 'string', 'Donation payment UUID.', required: true, example: 'd4e5f6a7-b8c9-40d1-92e3-f4a5b6c7d8e9')]
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
