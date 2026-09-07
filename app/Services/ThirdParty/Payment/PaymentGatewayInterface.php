<?php

namespace App\Services\ThirdParty\Payment;

use App\Http\Controllers\v1\Webhooks\PaystackWebhookController;

/**
 * Contract for a payment gateway: 1) initialize() starts a payment, returning either a hosted
 * `authorization_url` to redirect the donor to, or an embedded in-app payload (client_secret
 * for Stripe, access_code for Paystack), per that gateway's own `checkout_mode` config; card
 * data never touches our server either way. 2) The donor pays. 3) verify() calls the gateway
 * back with the reference for the *authoritative* status; only this step may mark a payment
 * successful, a browser redirect alone is not proof (see {@see PaystackWebhookController} for
 * the other, server-to-server, way verify() gets triggered).
 */
interface PaymentGatewayInterface
{
    /**
     * `gateway_transaction_id` is the gateway's own object id (e.g. Stripe's PaymentIntent or
     * Checkout Session id), when the gateway has one available at creation time; null gateways
     * (Paystack) look themselves up by `reference` well enough that verify() needs nothing else.
     * Persisted by the caller so verify() can be passed it back for a direct, consistent lookup.
     *
     * @return array{authorization_url: ?string, access_code: ?string, client_secret: ?string, publishable_key: ?string, reference: string, gateway_transaction_id: ?string}
     */
    public function initialize(string $reference, string $amount, string $currency, string $email, array $meta = []): array;

    /**
     * `$gatewayTransactionId`, when the gateway needs one (Stripe: required, retrieves the
     * payment directly by its own id rather than searching by `reference`), is whatever
     * initialize() returned as `gateway_transaction_id`; null for a gateway that doesn't need it
     * (Paystack, which looks itself up by `reference` well enough already).
     *
     * @return array{status: string, amount: ?string, currency: ?string, paid_at: ?string, channel: ?string, card_last_four: ?string, authorization: array{authorization_code: ?string, signature: ?string, reusable: bool, card_type: ?string, last4: ?string, exp_month: ?string, exp_year: ?string, bin: ?string, bank: ?string}}
     */
    public function verify(string $reference, ?string $gatewayTransactionId = null): array;

    /**
     * Charges a previously-saved, reusable payment method off-session (no donor present), for
     * recurring donation cycles. `$savedMethodToken` is `PaymentMethod::authorization_code`
     * (Paystack's authorization code, or Stripe's PaymentMethod id, whichever gateway saved
     * it). Unlike verify(), this reports the outcome directly from the gateway's synchronous
     * charge response rather than needing a follow-up lookup, since a declined/expired card is
     * an expected possible outcome here, not a system error.
     *
     * @param  array<string, mixed>  $meta
     * @return array{status: string, amount: ?string, currency: ?string, paid_at: ?string, channel: ?string, card_last_four: ?string, authorization: array{authorization_code: ?string, signature: ?string, reusable: bool, card_type: ?string, last4: ?string, exp_month: ?string, exp_year: ?string, bin: ?string, bank: ?string}}
     */
    public function charge(string $reference, string $amount, string $currency, string $email, string $savedMethodToken, array $meta = []): array;

    /**
     * Each gateway signs webhooks differently (Paystack: HMAC-SHA512 header; Stripe: a
     * signed-timestamp scheme), so verification is owned by the concrete gateway, not shared here.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool;
}
