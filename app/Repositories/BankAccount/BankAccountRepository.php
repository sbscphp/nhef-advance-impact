<?php

namespace App\Repositories\BankAccount;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\BankAccount;
use App\Repositories\Contracts\BankAccount\BankAccountRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class BankAccountRepository implements BankAccountRepositoryInterface
{
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        $query = BankAccount::query()
            ->select('bank_accounts.*')
            ->with('bank')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('account_number', 'like', '%'.$search.'%')
                        ->orWhere('account_name', 'like', '%'.$search.'%')
                        ->orWhereHas('bank', fn (Builder $bank) => $bank->where('name', 'like', '%'.$search.'%'));
                });
            });

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'bank_accounts.created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('bank_accounts.account_name', $direction),
            'bank' => fn ($query, string $direction) => $query
                ->leftJoin('banks', 'banks.id', '=', 'bank_accounts.bank_id')
                ->orderBy('banks.name', $direction),
        ], 'bank_accounts.created_at');

        return $query->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?BankAccount
    {
        return BankAccount::query()->with('bank')->where('uuid', $uuid)->first();
    }

    public function existsForBankAndNumber(int $bankId, string $accountNumber): bool
    {
        return BankAccount::query()
            ->where('bank_id', $bankId)
            ->where('account_number', $accountNumber)
            ->exists();
    }

    public function create(array $data): BankAccount
    {
        return BankAccount::query()->create($data);
    }
}
