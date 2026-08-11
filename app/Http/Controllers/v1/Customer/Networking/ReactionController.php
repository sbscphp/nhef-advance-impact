<?php

namespace App\Http\Controllers\v1\Customer\Networking;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Networking\ReactionRequest;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Networking\NetworkingService;
use Illuminate\Http\Request;

class ReactionController extends Controller
{
    public function __construct(private readonly NetworkingService $networkingService) {}

    public function store(ReactionRequest $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $this->networkingService->react($user, $uuid, $request->validated()['emoji']);

            return JsonResponser::send(false, 'Reaction added.', null, 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Networking\ReactionController@store');
        }
    }

    public function destroy(Request $request, string $uuid, string $emoji)
    {
        try {
            $user = $this->requireCustomer($request);
            $this->networkingService->unreact($user, $uuid, $emoji);

            return JsonResponser::send(false, 'Reaction removed.', null, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Networking\ReactionController@destroy');
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
