<?php

namespace App\Http\Controllers\v1\Admin\Communications;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Communications\AddTaskNoteRequest;
use App\Http\Requests\Admin\Communications\CreateTaskRequest;
use App\Http\Requests\Admin\Communications\TaskListRequest;
use App\Http\Requests\Admin\Communications\UpdateTaskRequest;
use App\Http\Resources\Communications\AssignableAdminResource;
use App\Http\Resources\Communications\CommunicationTaskInstanceResource;
use App\Http\Resources\Communications\CommunicationTaskResource;
use App\Models\Admin;
use App\Responser\JsonResponser;
use App\Services\Communications\TaskService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

/** `view` is computed at read time, never stored. Recurrence only ever lives on the root task; acting on an instance redirects to its root. */
class TaskController extends Controller
{
    public function __construct(private readonly TaskService $taskService) {}

    public function index(TaskListRequest $request)
    {
        try {
            $paginator = $this->taskService->paginate($request->validated());

            return JsonResponser::send(false, 'Tasks retrieved.', $this->paginatedPayload($paginator, CommunicationTaskResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\TaskController@index');
        }
    }

    public function overview()
    {
        try {
            return JsonResponser::send(false, 'Task overview retrieved.', $this->taskService->overview());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\TaskController@overview');
        }
    }

    public function assignableAdmins()
    {
        try {
            $admins = $this->taskService->assignableAdmins();

            return JsonResponser::send(false, 'Assignable admins retrieved.', AssignableAdminResource::collection($admins)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\TaskController@assignableAdmins');
        }
    }

    public function store(CreateTaskRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $task = $this->taskService->create($request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Task created successfully.', CommunicationTaskResource::make($task)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\TaskController@store');
        }
    }

    public function show(string $uuid)
    {
        try {
            $task = $this->taskService->findForDisplay($uuid);

            return JsonResponser::send(false, 'Task retrieved.', CommunicationTaskResource::make($task)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\TaskController@show');
        }
    }

    public function update(UpdateTaskRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $task = $this->taskService->update($uuid, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Task updated successfully.', CommunicationTaskResource::make($task)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\TaskController@update');
        }
    }

    public function destroy(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $this->taskService->delete($uuid, $admin, $request);

            return JsonResponser::send(false, 'Task deleted successfully.', null);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\TaskController@destroy');
        }
    }

    public function markDone(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $task = $this->taskService->markDone($uuid, $admin, $request);

            return JsonResponser::send(false, 'Task marked as done.', CommunicationTaskResource::make($task)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\TaskController@markDone');
        }
    }

    public function pauseRecurrence(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $task = $this->taskService->pauseRecurrence($uuid, $admin, $request);

            return JsonResponser::send(false, 'Recurrence paused.', CommunicationTaskResource::make($task)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\TaskController@pauseRecurrence');
        }
    }

    public function resumeRecurrence(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $task = $this->taskService->resumeRecurrence($uuid, $admin, $request);

            return JsonResponser::send(false, 'Recurrence resumed.', CommunicationTaskResource::make($task)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\TaskController@resumeRecurrence');
        }
    }

    public function disableRecurrence(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $task = $this->taskService->disableRecurrence($uuid, $admin, $request);

            return JsonResponser::send(false, 'Recurrence disabled.', CommunicationTaskResource::make($task)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\TaskController@disableRecurrence');
        }
    }

    public function instances(string $uuid)
    {
        try {
            $instances = $this->taskService->listInstances($uuid);

            return JsonResponser::send(false, 'Recurring history retrieved.', CommunicationTaskInstanceResource::collection($instances)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\TaskController@instances');
        }
    }

    public function addNote(AddTaskNoteRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $task = $this->taskService->addNote($uuid, $request->validated()['body'], $admin, $request);

            return JsonResponser::send(false, 'Note added successfully.', CommunicationTaskResource::make($task)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\TaskController@addNote');
        }
    }

    /**
     * @param  class-string<JsonResource>  $resourceClass
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator, string $resourceClass): array
    {
        $payload = $paginator->toArray();
        /** @var AnonymousResourceCollection $resource */
        $resource = $resourceClass::collection($paginator);
        $payload['data'] = $resource->resolve();

        return $payload;
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
