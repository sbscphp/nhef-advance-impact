<?php

namespace App\Http\Controllers\v1\Admin\Settings;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateWelcomeMessageRequest;
use App\Models\Admin;
use App\Responser\JsonResponser;
use App\Services\Settings\WelcomeMessageService;
use Illuminate\Http\Request;

class WelcomeMessageController extends Controller
{
    public function __construct(private readonly WelcomeMessageService $welcomeMessageService) {}

    public function show(Request $request)
    {
        try {
            $admin = $this->requireAdmin($request);

            return JsonResponser::send(false, 'Welcome message retrieved.', $this->welcomeMessageService->forAdmin($admin));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Settings\WelcomeMessageController@show');
        }
    }

    public function update(UpdateWelcomeMessageRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $result = $this->welcomeMessageService->update($request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Welcome message updated.', $result);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Settings\WelcomeMessageController@update');
        }
    }

    private function requireAdmin(Request $request): Admin
    {
        $admin = $request->user();
        if (! $admin instanceof Admin) {
            abort(403, 'Forbidden.');
        }

        return $admin;
    }
}
