<?php

namespace App\Repositories\Contracts\BankAccount;

use App\Models\BankAccount;
use Illuminate\Pagination\LengthAwarePaginator;

interface BankAccountRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?BankAccount;

    public function existsForBankAndNumber(int $bankId, string $accountNumber): bool;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): BankAccount;
}
