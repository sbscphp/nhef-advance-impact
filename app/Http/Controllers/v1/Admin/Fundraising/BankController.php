<?php

namespace App\Http\Controllers\v1\Admin\Fundraising;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Banks\BankAccountListRequest;
use App\Http\Requests\Admin\Banks\CreateBankAccountRequest;
use App\Http\Requests\Admin\Banks\ResolveBankAccountRequest;
use App\Http\Resources\Admin\BankAccountResource;
use App\Http\Resources\Admin\BankResource;
use App\Models\Admin;
use App\Responser\JsonResponser;
use App\Services\Fundraising\BankService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class BankController extends Controller
{
    public function __construct(private readonly BankService $bankService) {}

    public function dropdown()
    {
        try {
            $banks = $this->bankService->dropdown();

            return JsonResponser::send(false, 'Banks retrieved.', BankResource::collection($banks)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\BankController@dropdown');
        }
    }

    public function accountList(BankAccountListRequest $request)
    {
        try {
            $paginator = $this->bankService->listAccounts($request->validated());

            return JsonResponser::send(false, 'Bank accounts retrieved.', $this->paginatedPayload($paginator));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\BankController@accountList');
        }
    }

    public function resolveAccount(ResolveBankAccountRequest $request)
    {
        try {
            $resolved = $this->bankService->resolveAccountName($request->validated());

            return JsonResponser::send(false, 'Account verified.', $resolved);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\BankController@resolveAccount');
        }
    }

    public function createAccount(CreateBankAccountRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $bankAccount = $this->bankService->createAccount($request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Bank account added.', BankAccountResource::make($bankAccount)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\BankController@createAccount');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator): array
    {
        $payload = $paginator->toArray();
        $payload['data'] = BankAccountResource::collection($paginator)->resolve();

        return $payload;
    }

    private function requireAdmin(Request $request): Admin
    {
        $admin = $request->user();
        if (! $admin instanceof Admin) {
            abort(403, 'Forbidden.');
        }

        return $admin;
    }
}
