<?php

namespace App\Repositories\PaymentMethod;

use App\Models\PaymentMethod;
use App\Repositories\Contracts\PaymentMethod\PaymentMethodRepositoryInterface;
use Illuminate\Support\Collection;

class PaymentMethodRepository implements PaymentMethodRepositoryInterface
{
    public function listForUser(int $userId): Collection
    {
        return PaymentMethod::query()
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();
    }

    public function findByUuidForUser(int $userId, string $uuid): ?PaymentMethod
    {
        return PaymentMethod::query()
            ->where('user_id', $userId)
            ->where('uuid', $uuid)
            ->first();
    }

    public function findByAuthorizationCode(string $authorizationCode): ?PaymentMethod
    {
        return PaymentMethod::query()
            ->where('authorization_code', $authorizationCode)
            ->first();
    }

    public function findBySignatureForUser(int $userId, string $signature): ?PaymentMethod
    {
        return PaymentMethod::query()
            ->where('user_id', $userId)
            ->where('signature', $signature)
            ->first();
    }

    public function create(array $data): PaymentMethod
    {
        return PaymentMethod::create($data);
    }

    public function update(PaymentMethod $paymentMethod, array $data): PaymentMethod
    {
        $paymentMethod->forceFill($data)->save();

        return $paymentMethod;
    }

    public function clearDefaultForUser(int $userId): void
    {
        PaymentMethod::query()
            ->where('user_id', $userId)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    public function markDefault(PaymentMethod $paymentMethod): PaymentMethod
    {
        $paymentMethod->forceFill(['is_default' => true])->save();

        return $paymentMethod;
    }

    public function delete(PaymentMethod $paymentMethod): void
    {
        $paymentMethod->delete();
    }
}
