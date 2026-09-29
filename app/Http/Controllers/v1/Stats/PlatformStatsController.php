<?php

namespace App\Http\Controllers\v1\Stats;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Responser\JsonResponser;
use App\Services\Stats\PlatformStatsService;

/**
 * Public: the landing page's banner stat cards, no account needed.
 */
class PlatformStatsController extends Controller
{
    public function __construct(private readonly PlatformStatsService $statsService) {}

    public function overview()
    {
        try {
            return JsonResponser::send(false, 'Platform stats retrieved.', $this->statsService->overview());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Stats\PlatformStatsController@overview');
        }
    }
}
