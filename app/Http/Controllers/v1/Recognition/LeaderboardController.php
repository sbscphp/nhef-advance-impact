<?php

namespace App\Http\Controllers\v1\Recognition;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recognition\LeaderboardListRequest;
use App\Http\Resources\Recognition\LeaderboardEntryResource;
use App\Responser\JsonResponser;
use App\Services\Recognition\RecognitionService;
use Illuminate\Pagination\LengthAwarePaginator;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;

/**
 * Public recognition wall (BRD REC-01): donors ranked by lifetime NGN giving. Anonymous
 * donors never appear here (see RecognitionService); guests (no account) can't be ranked
 * since there's nothing to attach a public identity to.
 */
#[Group('Recognition / Leaderboard', 'Public donor recognition wall, ranked by lifetime giving (BRD REC-01). No account required.')]
class LeaderboardController extends Controller
{
    public function __construct(private readonly RecognitionService $recognitionService) {}

    #[Endpoint('List leaderboard')]
    #[Unauthenticated]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Results per page (max 100).', required: false, example: 10)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Leaderboard retrieved.',
        'data' => [
            'current_page' => 1,
            'data' => [
                ['rank' => 1, 'donor_name' => 'Adeola Craig', 'tier' => 'Diamond Fellow', 'total' => '5000000.00', 'total_formatted' => 'NGN 5,000,000.00'],
            ],
            'per_page' => 10,
            'total' => 0,
        ],
    ], description: 'Paginated leaderboard, highest total first.')]
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
