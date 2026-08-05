<?php

namespace App\Services\ThirdParty\Payment;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Stripe Checkout integration (https://stripe.com/docs/api/checkout/sessions).
 *
 * Stripe Checkout Sessions don't accept a caller-supplied ID the way Paystack accepts a
 * caller-supplied `reference`, and the Checkout Session resource has no search endpoint. So
 * our reference is stamped onto the underlying PaymentIntent's metadata instead (via
 * `payment_intent_data.metadata`, see initialize()), and `verify()` looks it up there with the
 * PaymentIntent Search API. Search is eventually consistent (typically near-instant,
 * occasionally a few seconds behind), so the browser-redirect verify path can race a very
 * fresh payment; the webhook (server-to-server, carries the object directly, no search
 * involved) is the reliable settlement path regardless, matching the dual-path lifecycle
 * described on {@see PaymentGatewayService}.
 */
class StripeService implements PaymentGatewayInterface
{
    /**
     * Step 1 of 3 (see {@see PaymentGatewayInterface}). `setup_future_usage: off_session` plus
     * `customer_creation: always` lets a returning donor's card be reused later (see
     * PaymentMethodService::saveFromAuthorization()), mirroring what Paystack does by default.
     */
    public function initialize(string $reference, string $amount, string $currency, string $email, array $meta = []): array
    {
        try {
            $session = $this->client()->checkout->sessions->create([
                'mode' => 'payment',
                'client_reference_id' => $reference,
                'customer_email' => $email,
                'customer_creation' => 'always',
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'unit_amount' => $this->toSubunit($amount),
                        'product_data' => [
                            'name' => (string) config('app.name', 'NHEF Nexus'),
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'payment_intent_data' => [
                    'setup_future_usage' => 'off_session',
                    // Only field the PaymentIntent Search API can find this by later; Checkout
                    // Session's own `client_reference_id` isn't searchable.
                    'metadata' => ['reference' => $reference],
                ],
                'success_url' => rtrim((string) config('app.frontend_url'), '/').'/donations/callback?reference='.$reference,
                'cancel_url' => rtrim((string) config('app.frontend_url'), '/').'/donations/callback?reference='.$reference,
                'metadata' => $meta,
            ], ['idempotency_key' => $reference]);
        } catch (ApiErrorException $exception) {
            Log::error('Stripe initialize failed', ['reference' => $reference, 'message' => $exception->getMessage()]);

            throw new ApiException('Unable to initialize payment with the gateway. Please try again.', 502);
        }

        return [
            'authorization_url' => (string) $session->url,
            'access_code' => $session->id,
            'reference' => $reference,
        ];
    }

    /**
     * Step 3 of 3. `expand` pulls the card details back in the same round trip instead of a
     * separate PaymentMethod retrieve.
     */
    public function verify(string $reference): array
    {
        try {
            $results = $this->client()->paymentIntents->search([
                'query' => "metadata['reference']:'{$reference}'",
                'limit' => 1,
                'expand' => ['data.payment_method'],
            ]);
        } catch (ApiErrorException $exception) {
            Log::error('Stripe verify failed', ['reference' => $reference, 'message' => $exception->getMessage()]);

            throw new ApiException('Unable to verify payment with the gateway. Please try again.', 502);
        }

        $paymentIntent = $results->data[0] ?? null;

        if (! $paymentIntent instanceof PaymentIntent) {
            Log::error('Stripe verify found no matching payment intent', ['reference' => $reference]);

            throw new ApiException('Unable to verify payment with the gateway. Please try again.', 502);
        }

        $paymentMethod = is_object($paymentIntent->payment_method) ? $paymentIntent->payment_method : null;
        $card = $paymentMethod->card ?? null;

        return [
            'status' => $paymentIntent->status === 'succeeded' ? 'successful' : $paymentIntent->status,
            'amount' => isset($paymentIntent->amount) ? $this->fromSubunit((int) $paymentIntent->amount) : null,
            'currency' => $paymentIntent->currency !== null ? strtoupper($paymentIntent->currency) : null,
            'paid_at' => isset($paymentIntent->created) ? date('c', $paymentIntent->created) : null,
            'channel' => $paymentMethod->type ?? ($paymentIntent->payment_method_types[0] ?? null),
            'card_last_four' => $card->last4 ?? null,
            'authorization' => [
                'authorization_code' => $paymentMethod->id ?? null,
                'signature' => $card->fingerprint ?? null,
                'reusable' => $paymentIntent->setup_future_usage === 'off_session',
                'card_type' => $card->brand ?? null,
                'last4' => $card->last4 ?? null,
                'exp_month' => isset($card->exp_month) ? (string) $card->exp_month : null,
                'exp_year' => isset($card->exp_year) ? (string) $card->exp_year : null,
                'bin' => null,
                'bank' => null,
            ],
        ];
    }

    /**
     * Stripe signs webhooks with a timestamped HMAC-SHA256 scheme (the `Stripe-Signature`
     * header), verified here via the SDK rather than hand-rolled like Paystack's, since
     * Stripe's scheme also guards against replay via the embedded timestamp.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        try {
            Webhook::constructEvent($rawBody, $signature, (string) config('services.stripe.webhook_secret'));

            return true;
        } catch (SignatureVerificationException|\UnexpectedValueException) {
            return false;
        }
    }

    private function client(): StripeClient
    {
        return new StripeClient([
            'api_key' => (string) config('services.stripe.secret_key'),
            'api_base' => (string) config('services.stripe.base_url', 'https://api.stripe.com'),
        ]);
    }

    private function toSubunit(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function fromSubunit(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
