<?php

namespace App\Services\ThirdParty\Payment;

use App\Http\Controllers\v1\Webhooks\PaystackWebhookController;
use App\Support\PaymentMode;
use Illuminate\Support\Facades\Log;

/**
 * Mode-aware facade over the payment gateway ({@see PaymentMode}). In `stub`/`log` mode no
 * real gateway is called: initialize() fakes a checkout URL and verify() reports immediate
 * success, so the pledge/donation flow can be exercised end-to-end without live credentials.
 * The live gateway itself is resolved by {@see PaymentGatewayResolver} from `PAYMENT_GATEWAY`;
 * this class never names a concrete gateway.
 *
 * Live lifecycle: initialize() returns a hosted checkout URL or in-app payload (per that
 * gateway's checkout_mode, see {@see PaymentGatewayInterface}) plus which gateway served the
 * request, since verify() later needs that same gateway even if the configured default
 * changed. The donor pays without card details ever reaching our server; the gateway then
 * either redirects the browser to callback_url or POSTs a webhook (PaystackWebhookController /
 * StripeWebhookController) - both paths call the idempotent verify(), so whichever arrives
 * first wins. Going live also requires registering each gateway's webhook URL in its
 * dashboard (tunnel with ngrok to test locally, since neither reaches plain `localhost`).
 */
class PaymentGatewayService
{
    public function __construct(private readonly PaymentGatewayResolver $gatewayResolver) {}

    /**
     * @param  array<string, mixed>  $meta
     * @return array{authorization_url: ?string, access_code: ?string, client_secret: ?string, publishable_key: ?string, reference: string, gateway: string}
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
            'client_secret' => null,
            'publishable_key' => null,
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
            // Fake reusable authorization so PaymentMethodService's saved-card flow can be
            // exercised without a live gateway; signature is fixed so repeated stub payments
            // dedupe to one saved payment method, same as a real repeat card would.
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
     * Charges a saved payment method off-session (recurring donation cycles). Mirrors verify()'s
     * mode branching; stub/log mode reports the same fake success as verify()'s stub so the
     * recurring-charge flow is exercisable end-to-end without live credentials.
     *
     * @param  array<string, mixed>  $meta
     * @return array{status: string, amount: ?string, currency: ?string, paid_at: ?string, channel: ?string, card_last_four: ?string, authorization: array{authorization_code: ?string, signature: ?string, reusable: bool, card_type: ?string, last4: ?string, exp_month: ?string, exp_year: ?string, bin: ?string, bank: ?string}}
     */
    public function charge(string $reference, string $amount, string $currency, string $email, string $savedMethodToken, string $gateway, array $meta = []): array
    {
        if (PaymentMode::isLive()) {
            return $this->gatewayResolver->make($gateway)->charge($reference, $amount, $currency, $email, $savedMethodToken, $meta);
        }

        if (PaymentMode::current() === PaymentMode::LOG) {
            Log::info('Payment charge stubbed as successful (PAYMENT_MODE=log)', ['reference' => $reference, 'gateway' => $gateway]);
        }

        return [
            'status' => 'successful',
            'amount' => null,
            'currency' => null,
            'paid_at' => now()->toIso8601String(),
            'channel' => 'stub',
            'card_last_four' => '0000',
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
