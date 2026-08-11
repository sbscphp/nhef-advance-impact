<?php

namespace App\Http\Controllers\v1\Customer\Networking;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Networking\ReactionRequest;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Networking\NetworkingService;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group('Customer Networking / Reactions', 'React to and remove reactions from messages, using a closed set of emoji.')]
class ReactionController extends Controller
{
    public function __construct(private readonly NetworkingService $networkingService) {}

    #[Endpoint('React to a message')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Message UUID.', required: true, example: 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6')]
    #[BodyParam('emoji', 'string', 'One of like, love, fire, clap, laugh, sad.', required: true, example: 'fire')]
    #[Response(status: 201, content: ['error' => false, 'message' => 'Reaction added.', 'data' => null], description: 'Reaction recorded and broadcast.')]
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

    #[Endpoint('Remove a reaction')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Message UUID.', required: true, example: 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6')]
    #[UrlParam('emoji', 'string', 'The emoji to remove.', required: true, example: 'fire')]
    #[Response(status: 200, content: ['error' => false, 'message' => 'Reaction removed.', 'data' => null], description: 'Your reaction was removed and the change broadcast.')]
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
