<?php

namespace App\Services\Fundraising;

use App\Enums\AuditActionEnum;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Models\Admin;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Repositories\Contracts\Bank\BankRepositoryInterface;
use App\Repositories\Contracts\BankAccount\BankAccountRepositoryInterface;
use App\Services\ThirdParty\Payment\PaystackService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class BankService
{
    public function __construct(
        private readonly BankRepositoryInterface $bankRepository,
        private readonly BankAccountRepositoryInterface $bankAccountRepository,
        private readonly PaystackService $paystackService,
    ) {}

    /**
     * @return Collection<int, Bank>
     */
    public function dropdown(): Collection
    {
        return $this->bankRepository->listActive();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listAccounts(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->bankAccountRepository->paginate($filters, $perPage);
    }

    /**
     * Backs the live "verify account" step on the "Add New Bank" modal, once both the account
     * number and bank are filled in.
     *
     * @param  array<string, mixed>  $payload
     * @return array{account_number: string, account_name: string, bank: array{bank_id: string, name: string}}
     */
    public function resolveAccountName(array $payload): array
    {
        $bank = $this->bankRepository->findByUuid((string) $payload['bank_id']);

        if (! $bank instanceof Bank) {
            throw new ApiException('The selected bank does not exist.', 422);
        }

        $resolved = $this->paystackService->resolveAccountName((string) $payload['account_number'], (string) $bank->code);

        return [
            'account_number' => $resolved['account_number'],
            'account_name' => $resolved['account_name'],
            'bank' => ['bank_id' => $bank->uuid, 'name' => $bank->name],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createAccount(array $payload, Admin $actor, Request $request): BankAccount
    {
        $bank = $this->bankRepository->findByUuid((string) $payload['bank_id']);

        if (! $bank instanceof Bank) {
            throw new ApiException('The selected bank does not exist.', 422);
        }

        if ($this->bankAccountRepository->existsForBankAndNumber($bank->id, (string) $payload['account_number'])) {
            throw new ApiException('This bank account has already been added.', 422);
        }

        $bankAccount = $this->bankAccountRepository->create([
            'bank_id' => $bank->id,
            'account_number' => $payload['account_number'],
            'account_name' => $payload['account_name'],
            'created_by' => $actor->uuid,
        ]);
        $bankAccount->setRelation('bank', $bank);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::BANK_ACCOUNT_CREATED,
            $request,
            $actor->uuid,
            [
                'bank_account_uuid' => $bankAccount->uuid,
                'bank_name' => $bank->name,
                'account_number' => $bankAccount->account_number,
            ],
            'Bank account added.',
            BankAccount::class,
            $bankAccount->uuid,
            ModuleEnums::fundraising,
            200,
        );

        return $bankAccount;
    }
}
