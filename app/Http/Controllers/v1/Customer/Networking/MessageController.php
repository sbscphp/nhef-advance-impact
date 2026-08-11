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
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group('Customer Networking / Messages', 'Send and browse messages within a channel you belong to, mark it read, and ping a typing indicator.')]
class MessageController extends Controller
{
    public function __construct(private readonly NetworkingService $networkingService) {}

    #[Endpoint('List messages')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Channel UUID.', required: true, example: 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6')]
    #[QueryParam('search', 'string', 'Filter by message text.', required: false, example: 'requirements')]
    #[QueryParam('sort_direction', 'string', 'asc (oldest first) or desc (newest first, default).', required: false, example: 'asc')]
    #[QueryParam('period', 'string', 'Filters by message sent date. One of: 1day, 3days, 7days, 14days, 30days, 3months, 6months, 1year, lastyear, custom.', required: false, example: '7days')]
    #[QueryParam('start_date', 'string', 'Start date; required when period=custom.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'End date; required when period=custom.', required: false, example: '2026-08-01')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Results per page (max 100).', required: false, example: 20)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Messages retrieved.',
        'data' => ['current_page' => 1, 'data' => [], 'per_page' => 20, 'total' => 0],
    ], description: 'Most recent messages first.')]
    #[Response(status: 403, content: [
        'error' => true,
        'message' => 'You must join this channel to do that.',
        'data' => null,
    ], description: 'Not a member of the channel.')]
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

    #[Endpoint('Send a message')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Channel UUID.', required: true, example: 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6')]
    #[BodyParam('body', 'string', 'Message text (required if no attachment).', required: false, example: 'Sure thing, I will have a look today.')]
    #[BodyParam('attachment', 'file', 'File attachment (required if no body).', required: false)]
    #[Response(status: 201, content: [
        'error' => false,
        'message' => 'Message sent.',
        'data' => ['uuid' => 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6'],
    ], description: 'Message created and broadcast to other members.')]
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

    #[Endpoint('Mark channel read')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Channel UUID.', required: true, example: 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6')]
    #[Response(status: 200, content: ['error' => false, 'message' => 'Channel marked read.', 'data' => null], description: 'Updates your last-read timestamp for this channel.')]
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

    #[Endpoint('Send a typing signal')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Channel UUID.', required: true, example: 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6')]
    #[Response(status: 200, content: ['error' => false, 'message' => 'Typing signal sent.', 'data' => null], description: 'Broadcasts an ephemeral "user is typing" event; not persisted.')]
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
