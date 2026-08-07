<?php

namespace App\Http\Controllers\v1\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Events\EventTicketService;
use App\Services\Fundraising\DonationService;
use App\Services\Fundraising\PledgeService;
use App\Services\ThirdParty\Payment\PaymentGatewayService;
use App\Support\PaymentMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Paystack calls this URL directly (server-to-server) when a charge succeeds; the reliable
 * counterpart to the browser-redirect verify flow (see {@see PaymentGatewayService}). Not wired
 * up by code: register `{app_url}/api/v1/webhooks/paystack` in the Paystack Dashboard under
 * Settings -> API Keys & Webhooks (one-time, per environment; tunnel with ngrok to test locally).
 */
class PaystackWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayService $paymentGatewayService,
        private readonly PledgeService $pledgeService,
        private readonly DonationService $donationService,
        private readonly EventTicketService $eventTicketService,
    ) {}

    /**
     * Confirms `charge.success` against the gateway and settles the matching pledge, donation,
     * or event-ticket payment (by the `PLG_`/`DON_`/`TIX_` reference prefix). Signature-verified
     * only when `PAYMENT_MODE=live`; ignored otherwise since no real gateway sends these.
     */
    public function handle(Request $request)
    {
        if (! PaymentMode::isLive()) {
            Log::info('Paystack webhook received while PAYMENT_MODE is not live; ignoring.', ['event' => $request->input('event')]);

            return response()->json(['error' => false, 'message' => 'Acknowledged.', 'data' => null], 200);
        }

        // Paystack signs the raw body with our secret key (HMAC-SHA512), proving the request
        // wasn't forged by someone POSTing straight to this public URL.
        $signature = $request->header('x-paystack-signature');

        if (! $this->paymentGatewayService->verifyWebhookSignature($request->getContent(), $signature)) {
            Log::warning('Paystack webhook signature verification failed.');

            return response()->json(['error' => true, 'message' => 'Invalid signature.', 'data' => null], 401);
        }

        $event = (string) $request->input('event');
        $reference = (string) $request->input('data.reference');

        if ($event === 'charge.success' && $reference !== '') {
            if (str_starts_with($reference, 'DON_')) {
                $this->donationService->verifyPayment($reference, $request);
            } elseif (str_starts_with($reference, 'TIX_')) {
                $this->eventTicketService->verifyPayment($reference, $request);
            } else {
                $this->pledgeService->verifyPayment($reference, $request);
            }
        }

        return response()->json(['error' => false, 'message' => 'Acknowledged.', 'data' => null], 200);
    }
}
