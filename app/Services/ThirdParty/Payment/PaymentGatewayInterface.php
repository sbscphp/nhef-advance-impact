<?php

namespace App\Services\ThirdParty\Payment;

use App\Http\Controllers\v1\Webhooks\PaystackWebhookController;

/**
 * Contract for a payment gateway. The flow behind every implementation:
 *
 *   1. initialize(): call the gateway to start a payment. Each gateway supports both a hosted
 *      and an in-app flow, chosen per-gateway via its own `checkout_mode` config
 *      (STRIPE_CHECKOUT_MODE / PAYSTACK_CHECKOUT_MODE, both default `embedded`):
 *        - hosted: authorization_url is set; the donor is redirected to the gateway's own
 *          checkout page.
 *        - embedded: the donor pays inline, without leaving our app. For Stripe that's
 *          client_secret + publishable_key (Stripe.js/Elements); for Paystack that's
 *          access_code + publishable_key (Paystack Inline's resumeTransaction()).
 *      No card data ever touches our server in either mode.
 *   2. Donor pays, either on the gateway's hosted page or inline in our own UI.
 *   3. verify(): call the gateway back with the reference to get the *authoritative*
 *      status. This is the only step that should ever mark a payment as successful; a
 *      browser redirect back to our app is not proof of payment on its own.
 *
 * See {@see PaystackService} for the concrete Paystack
 * implementation and {@see PaystackWebhookController} for
 * the other (server-to-server) way verify() gets triggered.
 */
interface PaymentGatewayInterface
{
    /**
     * @return array{authorization_url: ?string, access_code: ?string, client_secret: ?string, publishable_key: ?string, reference: string}
     */
    public function initialize(string $reference, string $amount, string $currency, string $email, array $meta = []): array;

    /**
     * @return array{status: string, amount: ?string, currency: ?string, paid_at: ?string, channel: ?string, card_last_four: ?string, authorization: array{authorization_code: ?string, signature: ?string, reusable: bool, card_type: ?string, last4: ?string, exp_month: ?string, exp_year: ?string, bin: ?string, bank: ?string}}
     */
    public function verify(string $reference): array;

    /**
     * Each gateway signs its webhooks differently (Paystack: HMAC-SHA512 header; Stripe: its
     * own signed-timestamp scheme), so verification is owned by the concrete gateway rather
     * than shared here.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool;
}
