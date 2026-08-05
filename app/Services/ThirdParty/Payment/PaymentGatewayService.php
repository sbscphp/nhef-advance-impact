<?php

namespace App\Services\ThirdParty\Payment;

use App\Http\Controllers\v1\Webhooks\PaystackWebhookController;
use App\Services\ThirdParty\SMS\SmsService;
use App\Support\PaymentMode;
use Illuminate\Support\Facades\Log;

/**
 * Mode-aware facade over the payment gateway ({@see PaymentMode}), mirroring how
 * {@see SmsService} gates provider dispatch. In `stub`/`log`
 * mode no real gateway is called: initialize() returns a fake checkout URL and verify()
 * reports an immediate success, so the pledge/donation flow can be exercised end-to-end
 * (e.g. via Postman) without live gateway credentials.
 *
 * Which live gateway is used is resolved by {@see PaymentGatewayResolver} from
 * `PAYMENT_GATEWAY` (config `services.payment.default`); this class never names a concrete
 * gateway, so switching the default (or adding a new one) doesn't touch this class.
 *
 * Full payment lifecycle (PAYMENT_MODE=live):
 *   1. PledgeService creates a pending PledgePayment row and calls initialize() here ->
 *      the configured gateway -> gets back an authorization_url (hosted checkout page). The
 *      gateway that served the request is returned alongside so the caller can persist it on
 *      the payment row; verify() later needs to be told that same gateway back, since the
 *      configured default may have changed in the meantime.
 *   2. Donor pays on that page. Card/bank details never reach our server.
 *   3. The gateway redirects the browser to our callback_url; separately (and more reliably),
 *      it also POSTs a webhook straight to our server (see PaystackWebhookController /
 *      StripeWebhookController). Either path ends up calling verify() here, which is
 *      idempotent, so whichever arrives first "wins".
 *
 * Next step for going live: register each gateway's webhook URL
 * (`{app_url}/api/v1/webhooks/{paystack|stripe}`) in that gateway's dashboard. Neither gateway
 * can reach a plain `localhost`, so use a tunnel (e.g. ngrok) to test webhooks locally.
 */
class PaymentGatewayService
{
    public function __construct(private readonly PaymentGatewayResolver $gatewayResolver) {}

    /**
     * @param  array<string, mixed>  $meta
     * @return array{authorization_url: string, access_code: ?string, reference: string, gateway: string}
     */
    public function initialize(string $reference, string $amount, string $currency, string $email, array $meta = []): array
    {
        $gateway = $this->gatewayResolver->default();

        if (PaymentMode::isLive()) {
            $result = $this->gatewayResolver->make($gateway)->initialize($reference, $amount, $currency, $email, $meta);

            return [...$result, 'gateway' => $gateway];
        }

        if (PaymentMode::current() === PaymentMode::LOG) {
            Log::info('Payment initialize stubbed (PAYMENT_MODE=log)', [
                'reference' => $reference,
                'amount' => $amount,
                'currency' => $currency,
                'email' => $email,
                'gateway' => $gateway,
            ]);
        }

        return [
            'authorization_url' => rtrim((string) config('app.frontend_url'), '/').'/stub-checkout/'.$reference,
            'access_code' => null,
            'reference' => $reference,
            'gateway' => $gateway,
        ];
    }

    /**
     * @return array{status: string, amount: ?string, currency: ?string, paid_at: ?string, channel: ?string, card_last_four: ?string, authorization: array{authorization_code: ?string, signature: ?string, reusable: bool, card_type: ?string, last4: ?string, exp_month: ?string, exp_year: ?string, bin: ?string, bank: ?string}}
     */
    public function verify(string $reference, string $gateway): array
    {
        if (PaymentMode::isLive()) {
            return $this->gatewayResolver->make($gateway)->verify($reference);
        }

        if (PaymentMode::current() === PaymentMode::LOG) {
            Log::info('Payment verify stubbed as successful (PAYMENT_MODE=log)', ['reference' => $reference, 'gateway' => $gateway]);
        }

        return [
            'status' => 'successful',
            'amount' => null,
            'currency' => null,
            'paid_at' => now()->toIso8601String(),
            'channel' => 'stub',
            'card_last_four' => '0000',
            // Fake reusable authorization so the saved-payment-method flow (see
            // PaymentMethodService) can be exercised end-to-end without a live gateway.
            // authorization_code is unique per call (mirrors a gateway minting a fresh one per
            // transaction), but signature is fixed; every stub payment "is" the same fake
            // card, so repeated stub payments should dedupe to one saved payment method.
            'authorization' => [
                'authorization_code' => 'AUTH_stub_'.$reference,
                'signature' => 'SIG_stub_fixed_test_card',
                'reusable' => true,
                'card_type' => 'visa DEBIT',
                'last4' => '0000',
                'exp_month' => '12',
                'exp_year' => (string) (now()->year + 2),
                'bin' => '408408',
                'bank' => 'Stub Bank',
            ],
        ];
    }

    /**
     * Verifies an inbound webhook's signature against the named gateway; each gateway owns its
     * own scheme (see the `verifyWebhookSignature` implementations on PaystackService and
     * StripeService).
     */
    public function verifyWebhookSignature(string $gateway, string $rawBody, ?string $signature): bool
    {
        if (! PaymentMode::isLive()) {
            return false;
        }

        return $this->gatewayResolver->make($gateway)->verifyWebhookSignature($rawBody, $signature);
    }
}
