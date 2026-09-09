<?php

namespace App\Http\Controllers\v1\Admin\Dashboard;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Responser\JsonResponser;
use App\Services\Dashboard\AdminDashboardService;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $dashboardService,
    ) {}

    #[Group('Admin Dashboard')]
    #[Authenticated]
    #[Endpoint('Get dashboard overview', 'National snapshot, donation intelligence and alumni intelligence stat cards for the admin dashboard home screen.')]
    #[Response(content: [
        'error' => false,
        'message' => 'Dashboard overview retrieved.',
        'data' => [
            'national_snapshot' => [
                'total_alumni' => 17,
                'total_institutions' => 15,
                'total_events' => 5,
                'active_campaigns' => 11,
                'total_mentors' => 1,
                'total_raised' => '0',
                'total_raised_formatted' => 'NGN 0.00',
            ],
            'donation_intelligence' => [
                'total_raised' => '0',
                'total_raised_formatted' => 'NGN 0.00',
                'raised_this_month' => '0',
                'raised_this_month_formatted' => 'NGN 0.00',
                'total_donors' => 0,
                'average_donation' => '0.00',
                'average_donation_formatted' => 'NGN 0.00',
            ],
            'alumni_intelligence' => [
                'all' => 17,
                'active' => 17,
                'access_revoked' => 0,
                'verified' => 5,
                'total_mentors' => 1,
            ],
        ],
    ], status: 200)]
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
