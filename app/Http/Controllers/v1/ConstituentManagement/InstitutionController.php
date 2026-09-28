<?php

namespace App\Http\Controllers\v1\ConstituentManagement;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Institutions\PublicInstitutionListRequest;
use App\Http\Resources\ConstituentManagement\FeaturedInstitutionResource;
use App\Responser\JsonResponser;
use App\Services\ConstituentManagement\InstitutionDirectoryService;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Public: the landing page's "Join Your University Community" directory, browsed by
 * anonymous website visitors, so nothing here needs an account.
 */
class InstitutionController extends Controller
{
    public function __construct(private readonly InstitutionDirectoryService $directoryService) {}

    public function index(PublicInstitutionListRequest $request)
    {
        try {
            $paginator = $this->directoryService->paginate($request->validated());

            return JsonResponser::send(false, 'Institutions retrieved.', $this->paginatedPayload($paginator), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'ConstituentManagement\InstitutionController@index');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator): array
    {
        $payload = $paginator->toArray();
        $payload['data'] = FeaturedInstitutionResource::collection($paginator)->resolve();

        return $payload;
    }
}
