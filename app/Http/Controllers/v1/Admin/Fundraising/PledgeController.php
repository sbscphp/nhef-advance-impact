<?php

namespace App\Http\Controllers\v1\Admin\Fundraising;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Pledges\PledgeListRequest;
use App\Http\Resources\Fundraising\PledgeResource;
use App\Responser\JsonResponser;
use App\Services\Fundraising\PledgeService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class PledgeController extends Controller
{
    public function __construct(
        private readonly PledgeService $pledgeService,
    ) {}

    public function index(PledgeListRequest $request)
    {
        try {
            $paginator = $this->pledgeService->paginateForAdmin($request->validated());

            return JsonResponser::send(false, 'Pledges retrieved.', $this->paginatedPayload($paginator, PledgeResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\PledgeController@index');
        }
    }

    /**
     * @param  class-string<JsonResource>  $resourceClass
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator, string $resourceClass): array
    {
        $payload = $paginator->toArray();
        /** @var AnonymousResourceCollection $resource */
        $resource = $resourceClass::collection($paginator);
        $payload['data'] = $resource->resolve();

        return $payload;
    }
}
