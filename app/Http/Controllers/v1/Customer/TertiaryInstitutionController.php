<?php

namespace App\Http\Controllers\v1\Customer;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\TertiaryInstitutionListRequest;
use App\Http\Resources\TertiaryInstitutionResource;
use App\Repositories\Contracts\TertiaryInstitution\TertiaryInstitutionRepositoryInterface;
use App\Responser\JsonResponser;
use Illuminate\Pagination\LengthAwarePaginator;

class TertiaryInstitutionController extends Controller
{
    public function __construct(private readonly TertiaryInstitutionRepositoryInterface $institutionRepository) {}

    public function index(TertiaryInstitutionListRequest $request)
    {
        try {
            $filters = $request->validated();
            $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
            $paginator = $this->institutionRepository->paginate($filters, $perPage);

            return JsonResponser::send(false, 'Institutions retrieved.', $this->paginatedPayload($paginator));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\TertiaryInstitutionController@index');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator): array
    {
        $payload = $paginator->toArray();
        $payload['data'] = TertiaryInstitutionResource::collection($paginator)->resolve();

        return $payload;
    }
}
