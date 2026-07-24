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
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;

/**
 * Paystack calls this URL directly (server-to-server) when a charge succeeds. It's the
 * reliable counterpart to the browser-redirect verify flow (see PaymentGatewayService's
 * class docblock for the full lifecycle). Not wired up by any code here: register
 * `{app_url}/api/v1/webhooks/paystack` in the Paystack Dashboard under Settings -> API Keys
 * & Webhooks. That's a one-time manual step, separate per environment (Paystack won't reach
 * a local `localhost` URL, so tunnel it with ngrok or similar to test this locally).
 */
#[Group('Webhooks', 'Inbound gateway webhooks. Not called by clients directly.')]
class PaystackWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayService $paymentGatewayService,
        private readonly PledgeService $pledgeService,
        private readonly DonationService $donationService,
        private readonly EventTicketService $eventTicketService,
    ) {}

    /**
     * Confirms `charge.success` events against the gateway and settles the matching pledge,
     * donation, or event-ticket payment (dispatched by the `PLG_`/`DON_`/`TIX_` prefix we mint
     * the reference with). Verified with the `x-paystack-signature` header (HMAC-SHA512) when
     * `PAYMENT_MODE=live`; requests are ignored otherwise since no real gateway sends them.
     */
    #[Endpoint('Paystack webhook')]
    #[Unauthenticated]
    #[Response(status: 200, content: ['error' => false, 'message' => 'Acknowledged.', 'data' => null], description: 'Event acknowledged (processed, ignored, or unverifiable).')]
    public function handle(Request $request)
    {
        if (! PaymentMode::isLive()) {
            Log::info('Paystack webhook received while PAYMENT_MODE is not live; ignoring.', ['event' => $request->input('event')]);

            return response()->json(['error' => false, 'message' => 'Acknowledged.', 'data' => null], 200);
        }

        // Paystack signs the raw request body with our secret key (HMAC-SHA512) and sends it
        // in this header, so we can trust the request actually came from Paystack and wasn't
        // forged by someone POSTing straight to this public URL.
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
