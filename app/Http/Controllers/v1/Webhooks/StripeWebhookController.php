<?php

namespace App\Http\Controllers\v1\Webhooks;

use App\Enums\PaymentGatewayEnum;
use App\Http\Controllers\Controller;
use App\Services\Events\EventTicketService;
use App\Services\Fundraising\DonationService;
use App\Services\Fundraising\PledgeService;
use App\Services\ThirdParty\Payment\PaymentGatewayService;
use App\Support\PaymentMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Stripe calls this URL directly (server-to-server) when a payment succeeds; the reliable
 * counterpart to the browser-redirect verify flow (see {@see PaymentGatewayService}). Not wired
 * up by code: register `{app_url}/api/v1/webhooks/stripe` in the Stripe Dashboard under
 * Developers -> Webhooks (listening for `payment_intent.succeeded`), copy the signing secret
 * into STRIPE_WEBHOOK_SECRET (one-time, per environment; tunnel with ngrok to test locally).
 */
class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayService $paymentGatewayService,
        private readonly PledgeService $pledgeService,
        private readonly DonationService $donationService,
        private readonly EventTicketService $eventTicketService,
    ) {}

    /**
     * Confirms `payment_intent.succeeded` and settles the matching pledge, donation, or
     * event-ticket payment (by the `PLG_`/`DON_`/`TIX_` reference prefix, stamped onto
     * `metadata.reference` by StripeService::initialize()). Signature-verified only when
     * `PAYMENT_MODE=live`; ignored otherwise since no real gateway sends these.
     */
    public function handle(Request $request)
    {
        if (! PaymentMode::isLive()) {
            Log::info('Stripe webhook received while PAYMENT_MODE is not live; ignoring.', ['event' => $request->input('type')]);

            return response()->json(['error' => false, 'message' => 'Acknowledged.', 'data' => null], 200);
        }

        // Stripe signs the raw body with a timestamped HMAC (the webhook's dashboard signing
        // secret), proving the request wasn't forged by someone POSTing straight to this URL.
        $signature = $request->header('Stripe-Signature');

        if (! $this->paymentGatewayService->verifyWebhookSignature(PaymentGatewayEnum::STRIPE->value, $request->getContent(), $signature)) {
            Log::warning('Stripe webhook signature verification failed.');

            return response()->json(['error' => true, 'message' => 'Invalid signature.', 'data' => null], 401);
        }

        $event = (string) $request->input('type');
        $reference = (string) $request->input('data.object.metadata.reference');

        if ($event === 'payment_intent.succeeded' && $reference !== '') {
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
