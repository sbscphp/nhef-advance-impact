<?php

namespace App\Services\Settings;

use App\Enums\AuditActionEnum;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Repositories\Contracts\PaymentMethod\PaymentMethodRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Saved cards ("My Payment Methods" settings screen). Populated by {@see saveFromAuthorization}
 * — called from PledgeService/DonationService::verifyPayment() whenever Paystack returns a
 * reusable authorization for a logged-in customer's successful payment. Guests never get a
 * saved method since they have no account to attach one to.
 */
class PaymentMethodService
{
    public function __construct(
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
    ) {}

    /**
     * @param  array{authorization_code: ?string, signature: ?string, reusable: bool, card_type: ?string, last4: ?string, exp_month: ?string, exp_year: ?string, bin: ?string, bank: ?string}  $authorization
     */
    public function saveFromAuthorization(User $user, array $authorization, string $gateway = 'paystack'): void
    {
        $code = $authorization['authorization_code'] ?? null;
        $signature = $authorization['signature'] ?? null;

        if (! $authorization['reusable'] || $code === null || $code === '') {
            return;
        }

        try {
            // Paystack mints a new authorization_code per transaction even for the same card —
            // `signature` is the actual same-card identifier, so that's what we dedupe on.
            // authorization_code is only a fallback match for the rare case signature is absent.
            $existing = $signature !== null && $signature !== ''
                ? $this->paymentMethodRepository->findBySignatureForUser($user->id, $signature)
                : $this->paymentMethodRepository->findByAuthorizationCode($code);

            if ($existing instanceof PaymentMethod) {
                $this->paymentMethodRepository->update($existing, [
                    // Refresh to the newest authorization_code — older ones can expire even
                    // though the card (signature) is still the same.
                    'authorization_code' => $code,
                    'signature' => $signature ?? $existing->signature,
                    'brand' => $authorization['card_type'] ?? $existing->brand,
                    'last_four' => $authorization['last4'] ?? $existing->last_four,
                    'exp_month' => $authorization['exp_month'] ?? $existing->exp_month,
                    'exp_year' => $authorization['exp_year'] ?? $existing->exp_year,
                    'bin' => $authorization['bin'] ?? $existing->bin,
                    'bank' => $authorization['bank'] ?? $existing->bank,
                ]);

                return;
            }

            $isFirstForUser = $this->paymentMethodRepository->listForUser($user->id)->isEmpty();

            $this->paymentMethodRepository->create([
                'user_id' => $user->id,
                'gateway' => $gateway,
                'authorization_code' => $code,
                'signature' => $signature,
                'brand' => $authorization['card_type'] ?? null,
                'last_four' => $authorization['last4'] ?? null,
                'exp_month' => $authorization['exp_month'] ?? null,
                'exp_year' => $authorization['exp_year'] ?? null,
                'bin' => $authorization['bin'] ?? null,
                'bank' => $authorization['bank'] ?? null,
                'is_default' => $isFirstForUser,
            ]);
        } catch (\Throwable $th) {
            Log::warning('Saving payment method from gateway authorization failed.', [
                'user_uuid' => $user->uuid,
                'exception' => $th::class,
                'message' => $th->getMessage(),
            ]);
        }
    }

    public function listForUser(User $user): Collection
    {
        return $this->paymentMethodRepository->listForUser($user->id);
    }

    public function setDefault(User $user, string $uuid, Request $request): PaymentMethod
    {
        $paymentMethod = $this->findForUser($user, $uuid);

        $paymentMethod = DB::transaction(function () use ($user, $paymentMethod): PaymentMethod {
            $this->paymentMethodRepository->clearDefaultForUser($user->id);

            return $this->paymentMethodRepository->markDefault($paymentMethod);
        });

        GeneralHelper::storeAuditLog(
            UserTypeEnum::CUSTOMER,
            AuditActionEnum::PAYMENT_METHOD_DEFAULT_SET,
            $request,
            $user->uuid,
            ['payment_method_uuid' => $paymentMethod->uuid],
            $user->displayName().' set a default payment method.',
            PaymentMethod::class,
            $paymentMethod->uuid,
            ModuleEnums::settings,
            200,
        );

        return $paymentMethod;
    }

    public function delete(User $user, string $uuid, Request $request): void
    {
        $paymentMethod = $this->findForUser($user, $uuid);
        $wasDefault = $paymentMethod->is_default;

        DB::transaction(function () use ($user, $paymentMethod, $wasDefault): void {
            $this->paymentMethodRepository->delete($paymentMethod);

            if (! $wasDefault) {
                return;
            }

            $next = $this->paymentMethodRepository->listForUser($user->id)->first();

            if ($next instanceof PaymentMethod) {
                $this->paymentMethodRepository->markDefault($next);
            }
        });

        GeneralHelper::storeAuditLog(
            UserTypeEnum::CUSTOMER,
            AuditActionEnum::PAYMENT_METHOD_DELETED,
            $request,
            $user->uuid,
            ['payment_method_uuid' => $paymentMethod->uuid],
            $user->displayName().' deleted a saved payment method.',
            PaymentMethod::class,
            $paymentMethod->uuid,
            ModuleEnums::settings,
            200,
        );
    }

    private function findForUser(User $user, string $uuid): PaymentMethod
    {
        $paymentMethod = $this->paymentMethodRepository->findByUuidForUser($user->id, $uuid);

        if (! $paymentMethod instanceof PaymentMethod) {
            throw new ApiException('Payment method not found.', 404);
        }

        return $paymentMethod;
    }
}
