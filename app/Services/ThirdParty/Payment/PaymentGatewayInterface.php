<?php

namespace App\Services\ThirdParty\Payment;


/**
 * Contract for a payment gateway: initialize() starts a payment (hosted redirect or embedded
 * payload, per `checkout_mode`); the donor pays; verify() calls the gateway back for the
 * *authoritative* status. Only verify() may mark a payment successful; a browser redirect alone
 * is not proof.
 */
interface PaymentGatewayInterface
{
    /**
     * `gateway_transaction_id` is the gateway's own object id (e.g. Stripe's PaymentIntent id),
     * when available; null for a gateway like Paystack that looks itself up by `reference`.
     *
     * @return array{authorization_url: ?string, access_code: ?string, client_secret: ?string, publishable_key: ?string, reference: string, gateway_transaction_id: ?string}
     */
    public function initialize(string $reference, string $amount, string $currency, string $email, array $meta = []): array;

    /**
     * `$gatewayTransactionId` is whatever initialize() returned; required for a gateway like
     * Stripe that retrieves by its own id, null for one like Paystack that looks up by `reference`.
     *
     * @return array{status: string, amount: ?string, currency: ?string, paid_at: ?string, channel: ?string, card_last_four: ?string, authorization: array{authorization_code: ?string, signature: ?string, reusable: bool, card_type: ?string, last4: ?string, exp_month: ?string, exp_year: ?string, bin: ?string, bank: ?string}}
     */
    public function verify(string $reference, ?string $gatewayTransactionId = null): array;

    /**
     * Charges a saved, reusable payment method off-session, for recurring donation cycles.
     * Unlike verify(), reports the outcome directly from the gateway's synchronous charge
     * response; a declined/expired card is an expected outcome here, not a system error.
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
