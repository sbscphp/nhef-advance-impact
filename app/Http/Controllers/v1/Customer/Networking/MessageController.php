<?php

namespace App\Http\Controllers\v1\Customer\Networking;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Networking\MessageListRequest;
use App\Http\Requests\Customer\Networking\SendMessageRequest;
use App\Http\Resources\Networking\NetworkingMessageResource;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Networking\NetworkingService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class MessageController extends Controller
{
    public function __construct(private readonly NetworkingService $networkingService) {}

    public function index(MessageListRequest $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $paginator = $this->networkingService->listMessages($user, $uuid, $request->validated());

            return JsonResponser::send(false, 'Messages retrieved.', $this->paginatedPayload($paginator), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Networking\MessageController@index');
        }
    }

    public function store(SendMessageRequest $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $message = $this->networkingService->sendMessage($user, $uuid, $request->validated(), $request->file('attachment'));

            return JsonResponser::send(false, 'Message sent.', NetworkingMessageResource::make($message), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Networking\MessageController@store');
        }
    }

    public function markRead(Request $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $this->networkingService->markRead($user, $uuid);

            return JsonResponser::send(false, 'Channel marked read.', null, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Networking\MessageController@markRead');
        }
    }

    public function typing(Request $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $this->networkingService->sendTypingSignal($user, $uuid);

            return JsonResponser::send(false, 'Typing signal sent.', null, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Networking\MessageController@typing');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator): array
    {
        $payload = $paginator->toArray();
        $payload['data'] = NetworkingMessageResource::collection($paginator)->resolve();

        return $payload;
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
