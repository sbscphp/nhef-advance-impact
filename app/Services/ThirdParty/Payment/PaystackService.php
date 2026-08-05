<?php

namespace App\Services\ThirdParty\Payment;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Paystack REST integration (https://paystack.com/docs/api/transaction/).
 * Settles NGN and USD; GBP/EUR pledges should route through a different gateway once one
 * is added behind {@see PaymentGatewayInterface}.
 */
class PaystackService implements PaymentGatewayInterface
{
    /**
     * Step 1 of 3 (see {@see PaymentGatewayInterface}). Paystack's docs: POST /transaction/initialize
     * (https://paystack.com/docs/api/transaction/#initialize).
     *
     * `callback_url` only controls where Paystack redirects the donor's *browser* after
     * checkout (`{callback_url}?reference=...&trxref=...`); it's a UX nicety, not how we
     * confirm payment. That redirect can be lost (closed tab, network blip), so it's not
     * trusted on its own; the frontend still has to call our verify endpoint, and the
     * webhook (see PaystackWebhookController) is the reliable backup for that same check.
     */
    public function initialize(string $reference, string $amount, string $currency, string $email, array $meta = []): array
    {
        $response = $this->client()->post('/transaction/initialize', [
            'reference' => $reference,
            'email' => $email,
            'amount' => $this->toSubunit($amount),
            'currency' => $currency,
            'callback_url' => config('app.frontend_url').'/donations/callback',
            'metadata' => $meta,
        ]);

        if ($response->failed() || ! $response->json('status')) {
            Log::error('Paystack initialize failed', ['reference' => $reference, 'body' => $response->body()]);

            throw new ApiException('Unable to initialize payment with the gateway. Please try again.', 502);
        }

        $data = $response->json('data', []);

        return [
            'authorization_url' => (string) ($data['authorization_url'] ?? ''),
            'access_code' => $data['access_code'] ?? null,
            'reference' => (string) ($data['reference'] ?? $reference),
        ];
    }

    /**
     * Step 3 of 3. Paystack's docs: GET /transaction/verify/:reference
     * (https://paystack.com/docs/api/transaction/#verify). Called from two places, whichever
     * fires first: the customer-facing "Verify payment" endpoint (after the browser redirect)
     * and PaystackWebhookController (server-to-server, independent of the browser). Both call
     * PledgeService::verifyPayment(), which is idempotent; a second call is a harmless no-op.
     */
    public function verify(string $reference): array
    {
        $response = $this->client()->get('/transaction/verify/'.rawurlencode($reference));

        if ($response->failed() || ! $response->json('status')) {
            Log::error('Paystack verify failed', ['reference' => $reference, 'body' => $response->body()]);

            throw new ApiException('Unable to verify payment with the gateway. Please try again.', 502);
        }

        $data = $response->json('data', []);
        $gatewayStatus = (string) ($data['status'] ?? 'failed');
        $authorization = $data['authorization'] ?? [];

        return [
            'status' => $gatewayStatus === 'success' ? 'successful' : $gatewayStatus,
            'amount' => isset($data['amount']) ? $this->fromSubunit((int) $data['amount']) : null,
            'currency' => $data['currency'] ?? null,
            'paid_at' => $data['paid_at'] ?? null,
            'channel' => $data['channel'] ?? null,
            'card_last_four' => $authorization['last4'] ?? null,
            // Only present (and only reusable: true) when Paystack allows this card to be
            // charged again later; see PaymentMethodService::saveFromAuthorization().
            // `authorization_code` is minted fresh per transaction even for the same card;
            // `signature` is Paystack's actual same-card identifier and what dedup keys off.
            'authorization' => [
                'authorization_code' => $authorization['authorization_code'] ?? null,
                'signature' => $authorization['signature'] ?? null,
                'reusable' => (bool) ($authorization['reusable'] ?? false),
                'card_type' => $authorization['card_type'] ?? null,
                'last4' => $authorization['last4'] ?? null,
                'exp_month' => $authorization['exp_month'] ?? null,
                'exp_year' => $authorization['exp_year'] ?? null,
                'bin' => $authorization['bin'] ?? null,
                'bank' => $authorization['bank'] ?? null,
            ],
        ];
    }

    /**
     * Paystack signs the raw request body with our secret key (HMAC-SHA512) and sends it in
     * the `x-paystack-signature` header, so we can trust the request actually came from
     * Paystack and wasn't forged by someone POSTing straight to the webhook URL.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha512', $rawBody, (string) config('services.paystack.secret_key'));

        return hash_equals($expected, $signature);
    }

    private function client()
    {
        return Http::baseUrl((string) config('services.paystack.base_url'))
            ->withToken((string) config('services.paystack.secret_key'))
            ->acceptJson();
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
