<?php

namespace App\Repositories\Contracts\PaymentMethod;

use App\Models\PaymentMethod;
use Illuminate\Support\Collection;

interface PaymentMethodRepositoryInterface
{
    public function listForUser(int $userId): Collection;

    public function findByUuidForUser(int $userId, string $uuid): ?PaymentMethod;

    public function findByAuthorizationCode(string $authorizationCode): ?PaymentMethod;

    public function findBySignatureForUser(int $userId, string $signature): ?PaymentMethod;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PaymentMethod;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PaymentMethod $paymentMethod, array $data): PaymentMethod;

    public function clearDefaultForUser(int $userId): void;

    public function markDefault(PaymentMethod $paymentMethod): PaymentMethod;

    public function delete(PaymentMethod $paymentMethod): void;
}
