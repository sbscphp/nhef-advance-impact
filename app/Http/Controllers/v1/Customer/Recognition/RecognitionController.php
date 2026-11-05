<?php

namespace App\Http\Controllers\v1\Customer\Recognition;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recognition\RecognitionMyCampaignsRequest;
use App\Http\Resources\Recognition\RecognitionCampaignResource;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Recognition\RecognitionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class RecognitionController extends Controller
{
    public function __construct(private readonly RecognitionService $recognitionService) {}

    public function me(Request $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $rank = $this->recognitionService->myRank($user);

            return JsonResponser::send(false, 'Rank retrieved.', $rank, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Recognition\RecognitionController@me');
        }
    }

    public function myCampaigns(RecognitionMyCampaignsRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $paginator = $this->recognitionService->myCampaigns($user, $request->validated());

            return JsonResponser::send(false, 'Campaign rankings retrieved.', $this->paginatedPayload($paginator, RecognitionCampaignResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Recognition\RecognitionController@myCampaigns');
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

    private function requireCustomer(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403, 'Forbidden.');
        }

        return $user;
    }
}
