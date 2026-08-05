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
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;

/**
 * Stripe calls this URL directly (server-to-server) when a payment succeeds. It's the
 * reliable counterpart to the browser-redirect verify flow (see PaymentGatewayService's class
 * docblock for the full lifecycle). Not wired up by any code here: register
 * `{app_url}/api/v1/webhooks/stripe` in the Stripe Dashboard under Developers -> Webhooks,
 * listening for `payment_intent.succeeded`, then copy the signing secret into
 * STRIPE_WEBHOOK_SECRET. That's a one-time manual step, separate per environment (Stripe won't
 * reach a local `localhost` URL, so tunnel it with ngrok or similar to test this locally).
 */
#[Group('Webhooks', 'Inbound gateway webhooks. Not called by clients directly.')]
class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayService $paymentGatewayService,
        private readonly PledgeService $pledgeService,
        private readonly DonationService $donationService,
        private readonly EventTicketService $eventTicketService,
    ) {}

    /**
     * Confirms `payment_intent.succeeded` events and settles the matching pledge, donation, or
     * event-ticket payment (dispatched by the `PLG_`/`DON_`/`TIX_` prefix we mint the reference
     * with; stamped onto `metadata.reference` by StripeService::initialize()). Verified with
     * the `Stripe-Signature` header when `PAYMENT_MODE=live`; requests are ignored otherwise
     * since no real gateway sends them.
     */
    #[Endpoint('Stripe webhook')]
    #[Unauthenticated]
    #[Response(status: 200, content: ['error' => false, 'message' => 'Acknowledged.', 'data' => null], description: 'Event acknowledged (processed, ignored, or unverifiable).')]
    public function handle(Request $request)
    {
        if (! PaymentMode::isLive()) {
            Log::info('Stripe webhook received while PAYMENT_MODE is not live; ignoring.', ['event' => $request->input('type')]);

            return response()->json(['error' => false, 'message' => 'Acknowledged.', 'data' => null], 200);
        }

        // Stripe signs the raw request body with a timestamped HMAC (the signing secret from
        // the webhook's dashboard settings), so we can trust the request actually came from
        // Stripe and wasn't forged by someone POSTing straight to this public URL.
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
