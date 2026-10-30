<?php

namespace App\Services\ThirdParty\Payment;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Paystack REST integration (https://paystack.com/docs/api/transaction/). Settles NGN and USD;
 * GBP/EUR pledges need a different gateway behind {@see PaymentGatewayInterface}.
 * `services.paystack.checkout_mode` picks whether `access_code` (embedded, Paystack Inline
 * popup) or `authorization_url` (hosted redirect) is handed to the frontend.
 */
class PaystackService implements PaymentGatewayInterface
{
    /**
     * Step 1 of 3 (see {@see PaymentGatewayInterface}). `callback_url` is only a UX nicety that
     * can be lost (closed tab); the frontend's verify call and the webhook are the real confirmation.
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
        $isEmbedded = $this->isEmbedded();

        return [
            'authorization_url' => $isEmbedded ? null : (string) ($data['authorization_url'] ?? ''),
            'access_code' => $isEmbedded ? ($data['access_code'] ?? null) : null,
            'client_secret' => null,
            'publishable_key' => $isEmbedded ? (string) config('services.paystack.public_key') : null,
            'reference' => (string) ($data['reference'] ?? $reference),
            // Paystack's own /transaction/verify/:reference lookup is already immediately
            // consistent, so there's no separate id worth capturing here.
            'gateway_transaction_id' => null,
        ];
    }

    private function isEmbedded(): bool
    {
        return (string) config('services.paystack.checkout_mode', 'embedded') !== 'hosted';
    }

    /**
     * Step 3 of 3. Called from whichever fires first: the "Verify payment" endpoint or
     * PaystackWebhookController; both funnel into the idempotent verifyPayment(), so a
     * second call is a no-op.
     */
    public function verify(string $reference, ?string $gatewayTransactionId = null): array
    {
        $response = $this->client()->get('/transaction/verify/'.rawurlencode($reference));

        if ($response->failed() || ! $response->json('status')) {
            Log::error('Paystack verify failed', ['reference' => $reference, 'body' => $response->body()]);

            throw new ApiException('Unable to verify payment with the gateway. Please try again.', 502);
        }

        return $this->mapTransactionDataToResult($response->json('data', []));
    }

    /**
     * Off-session variant of verify(), for recurring donation cycles. A decline is reported via
     * the response body's `status`, same as verify(), not a distinct error path.
     *
     * @param  array<string, mixed>  $meta
     */
    public function charge(string $reference, string $amount, string $currency, string $email, string $savedMethodToken, array $meta = []): array
    {
        $response = $this->client()->post('/transaction/charge_authorization', [
            'authorization_code' => $savedMethodToken,
            'reference' => $reference,
            'email' => $email,
            'amount' => $this->toSubunit($amount),
            'currency' => $currency,
            'metadata' => $meta,
        ]);

        if ($response->failed() || ! $response->json('status')) {
            Log::error('Paystack charge failed', ['reference' => $reference, 'body' => $response->body()]);

            throw new ApiException('Unable to charge the gateway. Please try again.', 502);
        }

        return $this->mapTransactionDataToResult($response->json('data', []));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: string, amount: ?string, currency: ?string, paid_at: ?string, channel: ?string, card_last_four: ?string, authorization: array{authorization_code: ?string, signature: ?string, reusable: bool, card_type: ?string, last4: ?string, exp_month: ?string, exp_year: ?string, bin: ?string, bank: ?string}}
     */
    private function mapTransactionDataToResult(array $data): array
    {
        $gatewayStatus = (string) ($data['status'] ?? 'failed');
        $authorization = $data['authorization'] ?? [];

        return [
            'status' => $gatewayStatus === 'success' ? 'successful' : $gatewayStatus,
            'amount' => isset($data['amount']) ? $this->fromSubunit((int) $data['amount']) : null,
            'currency' => $data['currency'] ?? null,
            'paid_at' => $data['paid_at'] ?? null,
            'channel' => $data['channel'] ?? null,
            'card_last_four' => $authorization['last4'] ?? null,
            // `signature`, not `authorization_code` (minted fresh per transaction), is the
            // real same-card identifier and what PaymentMethodService dedupes on.
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
     * Populates the local `banks` table with the CBN-recognized list of Nigerian banks (see
     * `banks:sync`). Filtered to `type: nuban` and `active: true`.
     *
     * @return list<array{name: string, code: string}>
     */
    public function listBanks(): array
    {
        $response = $this->client()->get('/bank', [
            'country' => 'nigeria',
            'currency' => 'NGN',
            'perPage' => 100,
        ]);

        if ($response->failed() || ! $response->json('status')) {
            Log::error('Paystack list banks failed', ['body' => $response->body()]);

            throw new ApiException('Unable to fetch the bank list from the gateway. Please try again.', 502);
        }

        return collect($response->json('data', []))
            ->filter(fn (array $bank) => ($bank['type'] ?? null) === 'nuban' && ($bank['active'] ?? false) === true)
            ->map(fn (array $bank) => ['name' => (string) $bank['name'], 'code' => (string) $bank['code']])
            ->values()
            ->all();
    }

    /**
     * Confirms an account number belongs to the named bank and returns the account holder's
     * registered name, so the admin can't remit to a mistyped account.
     *
     * @return array{account_number: string, account_name: string}
     */
    public function resolveAccountName(string $accountNumber, string $bankCode): array
    {
        $response = $this->client()->get('/bank/resolve', [
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
        ]);

        if ($response->failed() || ! $response->json('status')) {
            Log::error('Paystack resolve account failed', [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
                'body' => $response->body(),
            ]);

            throw new ApiException('Unable to verify this account number with the selected bank.', 422);
        }

        $data = $response->json('data', []);

        return [
            'account_number' => (string) ($data['account_number'] ?? $accountNumber),
            'account_name' => (string) ($data['account_name'] ?? ''),
        ];
    }

    /**
     * Paystack signs the raw body with our secret key (HMAC-SHA512) in the
     * `x-paystack-signature` header, proving the request wasn't forged.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha512', $rawBody, (string) config('services.paystack.secret_key'));

        return hash_equals($expected, $signature);
    }

    /**
     * `retry()` only catches a genuine connection failure (timeout, DNS, refused); a real
     * response, even a 4xx/5xx, is returned as-is. Mirrors StripeService's `max_network_retries`.
     */
    private function client()
    {
        return Http::baseUrl((string) config('services.paystack.base_url'))
            ->withToken((string) config('services.paystack.secret_key'))
            ->acceptJson()
            ->timeout(15)
            ->connectTimeout(5)
            ->retry(3, 500);
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
