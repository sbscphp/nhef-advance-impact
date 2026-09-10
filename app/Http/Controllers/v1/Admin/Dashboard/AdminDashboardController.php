<?php

namespace App\Http\Controllers\v1\Admin\Dashboard;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Responser\JsonResponser;
use App\Services\Dashboard\AdminDashboardService;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $dashboardService,
    ) {}

    public function overview()
    {
        try {
            $overview = $this->dashboardService->overview();

            return JsonResponser::send(false, 'Dashboard overview retrieved.', $overview);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Dashboard\AdminDashboardController@overview');
        }
    }
}
