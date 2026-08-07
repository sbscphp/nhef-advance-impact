<?php

namespace App\Http\Controllers\v1\Recognition;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recognition\LeaderboardListRequest;
use App\Http\Resources\Recognition\LeaderboardEntryResource;
use App\Responser\JsonResponser;
use App\Services\Recognition\RecognitionService;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Public recognition wall (BRD REC-01): donors ranked by lifetime NGN giving. Anonymous
 * donors never appear here; guests (no account) can't be ranked either.
 */
class LeaderboardController extends Controller
{
    public function __construct(private readonly RecognitionService $recognitionService) {}

    public function index(LeaderboardListRequest $request)
    {
        try {
            $paginator = $this->recognitionService->leaderboard($request->validated());

            return JsonResponser::send(false, 'Leaderboard retrieved.', $this->paginatedPayload($paginator), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Recognition\LeaderboardController@index');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator): array
    {
        $payload = $paginator->toArray();
        $payload['data'] = LeaderboardEntryResource::collection($paginator)->resolve();

        return $payload;
    }
}
