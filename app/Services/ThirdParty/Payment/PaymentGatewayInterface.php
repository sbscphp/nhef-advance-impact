<?php

namespace App\Services\ThirdParty\Payment;

use App\Http\Controllers\v1\Webhooks\PaystackWebhookController;

/**
 * Contract for a hosted-checkout payment gateway. The flow behind every implementation:
 *
 *   1. initialize() — call the gateway, get back a checkout page (authorization_url) the
 *      donor is sent to. No card data ever touches our server.
 *   2. Donor pays on the gateway's hosted page.
 *   3. verify() — call the gateway back with the reference to get the *authoritative*
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
     * @return array{authorization_url: string, access_code: ?string, reference: string}
     */
    public function initialize(string $reference, string $amount, string $currency, string $email, array $meta = []): array;

    /**
     * @return array{status: string, amount: ?string, currency: ?string, paid_at: ?string, channel: ?string, card_last_four: ?string, authorization: array{authorization_code: ?string, signature: ?string, reusable: bool, card_type: ?string, last4: ?string, exp_month: ?string, exp_year: ?string, bin: ?string, bank: ?string}}
     */
    public function verify(string $reference): array;
}
