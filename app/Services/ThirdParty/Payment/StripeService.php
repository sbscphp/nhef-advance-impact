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
 * Stripe integration. `services.stripe.checkout_mode` (env `STRIPE_CHECKOUT_MODE`, default
 * `embedded`) picks the donor flow: embedded uses PaymentIntent + Stripe Elements (donor pays
 * inline via `client_secret`/`publishable_key`, no redirect); hosted uses a Checkout Session
 * (donor redirected via `authorization_url`, same shape as Paystack's flow).
 *
 * Either way, `verify()` looks the PaymentIntent up by our own `reference` (stamped into its
 * metadata at creation) via the Search API, since Stripe won't let us pick our own PaymentIntent
 * ID and Sessions aren't searchable. Search is only eventually consistent, so the webhook
 * (server-to-server, no search) is the reliable settlement path regardless, matching the
 * dual-path lifecycle on {@see PaymentGatewayService}.
 */
class StripeService implements PaymentGatewayInterface
{
    /**
     * Step 1 of 3 (see {@see PaymentGatewayInterface}). Dispatches to the embedded or hosted
     * flow per `services.stripe.checkout_mode`; card data never touches our server either way.
     */
    public function initialize(string $reference, string $amount, string $currency, string $email, array $meta = []): array
    {
        return $this->isEmbedded()
            ? $this->initializeEmbedded($reference, $amount, $currency, $email, $meta)
            : $this->initializeHosted($reference, $amount, $currency, $email, $meta);
    }

    /**
     * Creates the Customer up front (not via Checkout's `customer_creation: always`) so
     * `setup_future_usage: off_session` has a Customer to attach the card to for reuse later
     * (see PaymentMethodService::saveFromAuthorization()), mirroring Paystack's default.
     */
    private function initializeEmbedded(string $reference, string $amount, string $currency, string $email, array $meta): array
    {
        try {
            $customer = $this->client()->customers->create(['email' => $email]);

            $paymentIntent = $this->client()->paymentIntents->create([
                'amount' => $this->toSubunit($amount),
                'currency' => strtolower($currency),
                'customer' => $customer->id,
                'receipt_email' => $email,
                'automatic_payment_methods' => ['enabled' => true],
                'setup_future_usage' => 'off_session',
                // Only field the PaymentIntent Search API can find this by later; Stripe
                // doesn't accept a caller-supplied PaymentIntent ID.
                'metadata' => [...$meta, 'reference' => $reference],
            ], ['idempotency_key' => $reference]);
        } catch (ApiErrorException $exception) {
            Log::error('Stripe initialize failed', ['reference' => $reference, 'message' => $exception->getMessage()]);

            throw new ApiException('Unable to initialize payment with the gateway. Please try again.', 502);
        }

        return [
            'authorization_url' => null,
            'access_code' => null,
            'client_secret' => (string) $paymentIntent->client_secret,
            'publishable_key' => (string) config('services.stripe.publishable_key'),
            'reference' => $reference,
        ];
    }

    private function initializeHosted(string $reference, string $amount, string $currency, string $email, array $meta): array
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
                            'name' => (string) config('app.name', 'NHEF Advance Impact'),
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
            'client_secret' => null,
            'publishable_key' => null,
            'reference' => $reference,
        ];
    }

    private function isEmbedded(): bool
    {
        return (string) config('services.stripe.checkout_mode', 'embedded') !== 'hosted';
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

    /**
     * `max_network_retries` also makes the SDK auto-attach an idempotency key to any un-keyed
     * POST (so `customers->create` is safely retryable too, not just `paymentIntents->create`),
     * so a transient blip is absorbed by the SDK's backoff instead of failing the donor outright.
     */
    private function client(): StripeClient
    {
        return new StripeClient([
            'api_key' => (string) config('services.stripe.secret_key'),
            'api_base' => (string) config('services.stripe.base_url', 'https://api.stripe.com'),
            'max_network_retries' => 2,
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
