<?php

namespace App\Http\Controllers\v1\Customer\Fundraising;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Pledges\MakePledgeRequest;
use App\Http\Requests\Customer\Pledges\PayInstallmentRequest;
use App\Http\Requests\Customer\Pledges\PledgeListRequest;
use App\Http\Resources\Fundraising\PledgeResource;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Fundraising\PledgeService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PledgeController extends Controller
{
    public function __construct(private readonly PledgeService $pledgeService) {}

    public function store(MakePledgeRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $result = $this->pledgeService->createPledge($user, $request->validated(), $request);

            return JsonResponser::send(false, 'Pledge created.', [
                'pledge' => PledgeResource::make($result['pledge']),
                'authorization_url' => $result['authorization_url'],
                'access_code' => $result['access_code'],
                'client_secret' => $result['client_secret'],
                'publishable_key' => $result['publishable_key'],
                'reference' => $result['reference'],
            ], 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Fundraising\PledgeController@store');
        }
    }

    public function index(PledgeListRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $paginator = $this->pledgeService->paginateForUser($user, $request->validated());

            return JsonResponser::send(false, 'Pledges retrieved.', $this->paginatedPayload($paginator), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Fundraising\PledgeController@index');
        }
    }

    public function show(Request $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $pledge = $this->pledgeService->findForUser($user, $uuid);

            return JsonResponser::send(false, 'Pledge retrieved.', PledgeResource::make($pledge), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Fundraising\PledgeController@show');
        }
    }

    public function payInstallment(PayInstallmentRequest $request, string $uuid, string $installmentUuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $result = $this->pledgeService->payInstallment($user, $uuid, $installmentUuid, $request);

            return JsonResponser::send(false, 'Payment initialized.', $result, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Fundraising\PledgeController@payInstallment');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator): array
    {
        $payload = $paginator->toArray();
        $payload['data'] = PledgeResource::collection($paginator)->resolve();

        return $payload;
    }

    private function requireCustomer(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403, 'Forbidden.');
        }

        return $user;
    }
}
