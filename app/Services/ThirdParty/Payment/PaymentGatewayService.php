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
 * Full payment lifecycle (PAYMENT_MODE=live):
 *   1. PledgeService creates a pending PledgePayment row and calls initialize() here ->
 *      Paystack -> gets back an authorization_url (hosted checkout page).
 *   2. Donor pays on that page. Card/bank details never reach our server.
 *   3. Paystack redirects the browser to our callback_url; separately (and more reliably),
 *      Paystack also POSTs a `charge.success` webhook straight to our server; see
 *      {@see PaystackWebhookController}. Either path ends
 *      up calling verify() here, which is idempotent, so whichever arrives first "wins".
 *
 * Next step for going live: register the webhook URL (`{app_url}/api/v1/webhooks/paystack`)
 * in the Paystack Dashboard under Settings -> API Keys & Webhooks. Paystack's servers need a
 * public HTTPS URL to reach it, so it won't fire against a plain `localhost`; use a tunnel
 * (e.g. ngrok) if you need to test the webhook path locally.
 */
class PaymentGatewayService
{
    public function __construct(private readonly PaystackService $paystackService) {}

    /**
     * @param  array<string, mixed>  $meta
     * @return array{authorization_url: string, access_code: ?string, reference: string}
     */
    public function initialize(string $reference, string $amount, string $currency, string $email, array $meta = []): array
    {
        if (PaymentMode::isLive()) {
            return $this->paystackService->initialize($reference, $amount, $currency, $email, $meta);
        }

        if (PaymentMode::current() === PaymentMode::LOG) {
            Log::info('Payment initialize stubbed (PAYMENT_MODE=log)', [
                'reference' => $reference,
                'amount' => $amount,
                'currency' => $currency,
                'email' => $email,
            ]);
        }

        return [
            'authorization_url' => rtrim((string) config('app.frontend_url'), '/').'/stub-checkout/'.$reference,
            'access_code' => null,
            'reference' => $reference,
        ];
    }

    /**
     * @return array{status: string, amount: ?string, currency: ?string, paid_at: ?string, channel: ?string, card_last_four: ?string, authorization: array{authorization_code: ?string, signature: ?string, reusable: bool, card_type: ?string, last4: ?string, exp_month: ?string, exp_year: ?string, bin: ?string, bank: ?string}}
     */
    public function verify(string $reference): array
    {
        if (PaymentMode::isLive()) {
            return $this->paystackService->verify($reference);
        }

        if (PaymentMode::current() === PaymentMode::LOG) {
            Log::info('Payment verify stubbed as successful (PAYMENT_MODE=log)', ['reference' => $reference]);
        }

        return [
            'status' => 'successful',
            'amount' => null,
            'currency' => null,
            'paid_at' => now()->toIso8601String(),
            'channel' => 'stub',
            'card_last_four' => '0000',
            // Fake reusable authorization so the saved-payment-method flow (see
            // PaymentMethodService) can be exercised end-to-end without live Paystack.
            // authorization_code is unique per call (mirrors Paystack minting a fresh one per
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
     * Verifies a Paystack webhook signature (HMAC-SHA512 of the raw body with the secret key).
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        if (! PaymentMode::isLive() || $signature === null || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha512', $rawBody, (string) config('services.paystack.secret_key'));

        return hash_equals($expected, $signature);
    }
}
