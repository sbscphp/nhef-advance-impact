<?php

namespace App\Http\Controllers\v1\Admin\Notification;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Notification\NotificationListRequest;
use App\Http\Resources\DatabaseNotificationResource;
use App\Models\Admin;
use App\Responser\JsonResponser;
use App\Services\Notifications\NotificationInboxService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group('Admin / Notifications', 'The admin in-app notification inbox: list, filter by read status/category, mark read/unread, and dismiss.')]
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationInboxService $inbox) {}

    #[Endpoint('List notifications')]
    #[Authenticated]
    #[QueryParam('period', 'string', 'Filters by date. One of: 1day, 3days, 7days, 14days, 30days, 3months, 6months, 1year, lastyear, custom.', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Start date; required when period=custom.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'End date; required when period=custom.', required: false, example: '2026-08-01')]
    #[QueryParam('filters[read_status]', 'string', 'One of: all, read, unread.', required: false, example: 'unread')]
    #[QueryParam('filters[category]', 'string', 'One of: system_notification, core_platform, alumni_advancement_suite, impact_grant, others.', required: false, example: 'core_platform')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Results per page (max 100).', required: false, example: 15)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Notifications retrieved.',
        'data' => [
            'current_page' => 1,
            'data' => [
                [
                    'id' => 'd059b404-58b1-4992-abe8-c898abbd3bcc',
                    'type' => 'login_success',
                    'title' => 'Successful login',
                    'message' => 'Your account just logged in successfully.',
                    'module' => 'authentication',
                    'category' => 'system_notification',
                    'is_read' => false,
                    'read_at' => null,
                    'email_notifications_enabled' => true,
                    'push_notifications_enabled' => true,
                    'created_at' => '2026-08-11T11:17:49+00:00',
                ],
            ],
            'per_page' => 15,
            'total' => 3,
            'categories' => [
                ['key' => 'system_notification', 'label' => 'System Notification', 'count' => 3],
                ['key' => 'core_platform', 'label' => 'Core Platform', 'count' => 0],
                ['key' => 'alumni_advancement_suite', 'label' => 'Alumni & Advancement Suite', 'count' => 0],
                ['key' => 'impact_grant', 'label' => 'Impact & Grant', 'count' => 0],
                ['key' => 'others', 'label' => 'Others', 'count' => 0],
            ],
            'unread_count' => 3,
            'read_count' => 0,
        ],
    ], description: 'Standard paginator, plus per-category counts (unaffected by the current filters) and unread/read totals for the currently filtered set.')]
    public function index(NotificationListRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $validated = $request->validated();
            $perPage = max(1, min((int) ($validated['per_page'] ?? 15), 100));
            $page = max(1, (int) ($validated['page'] ?? 1));
            $paginator = $this->inbox->paginate($admin, $perPage, $page, $validated);

            return JsonResponser::send(false, 'Notifications retrieved.', $this->paginatedPayload($paginator, $validated, $admin));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Notification\NotificationController@index');
        }
    }

    #[Endpoint('View a notification')]
    #[Authenticated]
    #[UrlParam('id', 'string', 'Notification UUID.', required: true, example: 'd059b404-58b1-4992-abe8-c898abbd3bcc')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Notification retrieved.',
        'data' => [
            'id' => 'd059b404-58b1-4992-abe8-c898abbd3bcc',
            'title' => 'Successful login',
            'message' => 'Your account just logged in successfully.',
            'module' => 'authentication',
            'category' => 'system_notification',
            'is_read' => false,
        ],
    ], description: 'Does not mark it read - use "Mark a notification as read" separately.')]
    public function show(Request $request, string $id)
    {
        try {
            $admin = $this->requireAdmin($request);

            return JsonResponser::send(false, 'Notification retrieved.', DatabaseNotificationResource::make($this->inbox->findForRecipient($admin, $id))->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Notification\NotificationController@show');
        }
    }

    #[Endpoint('Mark all as read')]
    #[Authenticated]
    #[Response(status: 200, content: ['error' => false, 'message' => 'All notifications marked as read.', 'data' => null], description: 'Marks every currently-unread notification as read.')]
    public function markAllRead(Request $request)
    {
        try {
            $this->inbox->markAllRead($this->requireAdmin($request));

            return JsonResponser::send(false, 'All notifications marked as read.', null);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Notification\NotificationController@markAllRead');
        }
    }

    #[Endpoint('Mark a notification as read')]
    #[Authenticated]
    #[UrlParam('id', 'string', 'Notification UUID.', required: true, example: 'd059b404-58b1-4992-abe8-c898abbd3bcc')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Notification marked as read.',
        'data' => ['id' => 'd059b404-58b1-4992-abe8-c898abbd3bcc', 'is_read' => true],
    ], description: 'Updated notification.')]
    public function markRead(Request $request, string $id)
    {
        try {
            $admin = $this->requireAdmin($request);

            return JsonResponser::send(false, 'Notification marked as read.', DatabaseNotificationResource::make($this->inbox->markRead($admin, $id))->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Notification\NotificationController@markRead');
        }
    }

    #[Endpoint('Mark a notification as unread')]
    #[Authenticated]
    #[UrlParam('id', 'string', 'Notification UUID.', required: true, example: 'd059b404-58b1-4992-abe8-c898abbd3bcc')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Notification marked as unread.',
        'data' => ['id' => 'd059b404-58b1-4992-abe8-c898abbd3bcc', 'is_read' => false],
    ], description: 'Updated notification.')]
    public function markUnread(Request $request, string $id)
    {
        try {
            $admin = $this->requireAdmin($request);

            return JsonResponser::send(false, 'Notification marked as unread.', DatabaseNotificationResource::make($this->inbox->markUnread($admin, $id))->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Notification\NotificationController@markUnread');
        }
    }

    #[Endpoint('Dismiss a notification', 'Permanently deletes the notification; this is not the same as marking it read.')]
    #[Authenticated]
    #[UrlParam('id', 'string', 'Notification UUID.', required: true, example: 'd059b404-58b1-4992-abe8-c898abbd3bcc')]
    #[Response(status: 200, content: ['error' => false, 'message' => 'Notification deleted.', 'data' => null], description: 'Notification removed.')]
    public function dismiss(Request $request, string $id)
    {
        try {
            $this->inbox->dismiss($this->requireAdmin($request), $id);

            return JsonResponser::send(false, 'Notification deleted.', null);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Notification\NotificationController@dismiss');
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

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator, array $validated, Admin $admin): array
    {
        $payload = $paginator->toArray();
        $payload['data'] = DatabaseNotificationResource::collection($paginator)->resolve();
        $payload['categories'] = $this->inbox->categoryCounts($admin);

        return array_merge($payload, $this->inbox->inboxCounts($admin, $validated));
    }
}
