<?php

namespace App\Http\Controllers\v1\Admin\Fundraising;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Banks\BankAccountListRequest;
use App\Http\Requests\Admin\Banks\CreateBankAccountRequest;
use App\Http\Resources\Admin\BankAccountResource;
use App\Http\Resources\Admin\BankResource;
use App\Models\Admin;
use App\Responser\JsonResponser;
use App\Services\Fundraising\BankService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;

#[Group('Admin / Fundraising / Bank Accounts', 'Bank lookup and reusable remittance bank accounts, backing step 3 ("Bank") of the "Add New Campaign" wizard. Requires the `campaigns.read`/`campaigns.create` permission.')]
class BankController extends Controller
{
    public function __construct(private readonly BankService $bankService) {}

    #[Endpoint('Bank dropdown', 'Lists active banks for the "Select Bank" field on the "Add New Bank" modal.')]
    #[Authenticated]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Banks retrieved.',
        'data' => [
            ['bank_id' => 'c3d4e5f6-a7b8-4c9d-0e1f-2a3b4c5d6e7f', 'name' => 'Access Bank', 'code' => '044'],
            ['bank_id' => 'd4e5f6a7-b8c9-4d0e-1f2a-3b4c5d6e7f8a', 'name' => 'United Bank for Africa', 'code' => '033'],
        ],
    ], description: 'Active banks.')]
    public function dropdown()
    {
        try {
            $banks = $this->bankService->dropdown();

            return JsonResponser::send(false, 'Banks retrieved.', BankResource::collection($banks)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\BankController@dropdown');
        }
    }

    #[Endpoint('List bank accounts', 'Paginated, searchable list of existing remittance bank accounts backing the "Search & Select Account" step. Search matches account number, account name, or bank name.')]
    #[Authenticated]
    #[QueryParam('search', 'string', 'Filter by account number, account name, or bank name.', required: false, example: 'UBA')]
    #[QueryParam('period', 'string', 'Relative date window; use `custom` with `start_date`/`end_date` for an explicit range.', required: false, example: '30days')]
    #[QueryParam('start_date', 'date', 'Only accounts added on/after this date (required if `period=custom`).', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'date', 'Only accounts added on/before this date (required if `period=custom`).', required: false, example: '2026-01-31')]
    #[QueryParam('sort_by', 'string', 'Order arrangement field: "name" (account name) or "bank" (bank name).', required: false, example: 'name')]
    #[QueryParam('sort_direction', 'string', 'Order arrangement direction: asc or desc.', required: false, example: 'desc')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Results per page (max 100).', required: false, example: 15)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Bank accounts retrieved.',
        'data' => [
            'current_page' => 1,
            'data' => [],
            'per_page' => 15,
            'total' => 0,
        ],
    ], description: 'Paginated list of bank accounts.')]
    public function accountList(BankAccountListRequest $request)
    {
        try {
            $paginator = $this->bankService->listAccounts($request->validated());

            return JsonResponser::send(false, 'Bank accounts retrieved.', $this->paginatedPayload($paginator));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\BankController@accountList');
        }
    }

    #[Endpoint('Add a new bank account', 'Backs the "Add New Bank" modal reached via "+ New Bank" on step 3. The created account becomes selectable (and reusable) for this and future campaigns.')]
    #[Authenticated]
    #[BodyParam('bank_id', 'string', 'Bank UUID from `GET /banks/dropdown`.', required: true, example: 'c3d4e5f6-a7b8-4c9d-0e1f-2a3b4c5d6e7f')]
    #[BodyParam('account_number', 'string', 'Account number.', required: true, example: '234567890')]
    #[BodyParam('account_name', 'string', 'Account holder name.', required: true, example: 'University of Lagos')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Bank account added.',
        'data' => [
            'bank_account_id' => 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7',
            'account_number' => '234567890',
            'account_name' => 'University of Lagos',
            'bank' => ['bank_id' => 'c3d4e5f6-a7b8-4c9d-0e1f-2a3b4c5d6e7f', 'name' => 'United Bank for Africa'],
        ],
    ], description: 'Bank account created.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'This bank account has already been added.',
        'data' => null,
    ], description: 'Duplicate bank + account number.')]
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
