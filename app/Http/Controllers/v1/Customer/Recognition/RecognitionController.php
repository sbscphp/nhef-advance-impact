<?php

namespace App\Http\Controllers\v1\Customer\Recognition;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Recognition\RecognitionService;
use Illuminate\Http\Request;

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

    private function requireCustomer(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403, 'Forbidden.');
        }

        return $user;
    }
}
