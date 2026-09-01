<?php

namespace App\Services\Communications;

use App\Enums\AuditActionEnum;
use App\Enums\ModuleEnums;
use App\Enums\TaskRecurrenceStatusEnum;
use App\Enums\TaskStatusEnum;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Models\Admin;
use App\Models\CommunicationTask;
use App\Repositories\Contracts\Admin\AdminRepositoryInterface;
use App\Repositories\Contracts\Communications\CommunicationTaskNoteRepositoryInterface;
use App\Repositories\Contracts\Communications\CommunicationTaskRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TaskService
{
    public function __construct(
        private readonly CommunicationTaskRepositoryInterface $taskRepository,
        private readonly CommunicationTaskNoteRepositoryInterface $noteRepository,
        private readonly AdminRepositoryInterface $adminRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, Admin $actor, Request $request): CommunicationTask
    {
        $assignee = $this->resolveAssignee($payload['assigned_to']);
        $isRecurring = (bool) ($payload['is_recurring'] ?? false);
        $repeatNonStop = $isRecurring && (bool) ($payload['repeat_non_stop'] ?? false);
        $remindersEnabled = (bool) ($payload['reminders_enabled'] ?? true);

        // Call-log follow-ups only expose the master toggle, no per-window picks; default all
        // 3 windows on when none were given, rather than leaving them silently off.
        $hasIndividualReminderKeys = array_key_exists('reminder_2_days_before', $payload)
            || array_key_exists('reminder_1_day_before', $payload)
            || array_key_exists('reminder_on_due_date', $payload);
        $defaultWindow = $remindersEnabled && ! $hasIndividualReminderKeys;

        $task = $this->taskRepository->create([
            'call_log_id' => $payload['call_log_id'] ?? null,
            'title' => $payload['title'],
            'assigned_to' => $assignee->uuid,
            'priority' => $payload['priority'],
            'description' => $payload['description'] ?? null,
            'start_date' => $payload['start_date'],
            'due_date' => $payload['due_date'],
            'status' => TaskStatusEnum::PENDING->value,
            'reminders_enabled' => $remindersEnabled,
            'reminder_2_days_before' => (bool) ($payload['reminder_2_days_before'] ?? $defaultWindow),
            'reminder_1_day_before' => (bool) ($payload['reminder_1_day_before'] ?? $defaultWindow),
            'reminder_on_due_date' => (bool) ($payload['reminder_on_due_date'] ?? $defaultWindow),
            'is_recurring' => $isRecurring,
            'repeat_non_stop' => $repeatNonStop,
            'recurrence_interval_value' => $isRecurring ? ($payload['recurrence_interval_value'] ?? null) : null,
            'recurrence_interval_unit' => $isRecurring ? ($payload['recurrence_interval_unit'] ?? null) : null,
            'recurrence_end_date' => ($isRecurring && ! $repeatNonStop) ? ($payload['recurrence_end_date'] ?? null) : null,
            'recurrence_status' => $isRecurring ? TaskRecurrenceStatusEnum::ACTIVE->value : null,
            'created_by' => $actor->uuid,
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::COMMUNICATION_TASK_CREATED,
            $request,
            $actor->uuid,
            ['task_uuid' => $task->uuid],
            $actor->displayName().' created task: '.$task->title.'.',
            CommunicationTask::class,
            $task->uuid,
            ModuleEnums::communications,
            201,
        );

        return $this->findForDisplay($task->uuid);
    }

    public function find(string $uuid): CommunicationTask
    {
        $task = $this->taskRepository->findByUuid($uuid);

        if (! $task instanceof CommunicationTask) {
            throw new ApiException('Task not found.', 404);
        }

        return $task;
    }

    /** Only call at a method's final return: sets a pseudo-attribute find() doesn't, which a later save() would try to persist as a real column. */
    public function findForDisplay(string $uuid): CommunicationTask
    {
        return $this->attachNextInstanceAt($this->find($uuid));
    }

    /** `next_instance_at` pseudo-attribute for CommunicationTaskResource; only set for an actively-recurring root. */
    private function attachNextInstanceAt(CommunicationTask $task): CommunicationTask
    {
        if (! $task->is_recurring || $task->recurrence_status !== TaskRecurrenceStatusEnum::ACTIVE->value) {
            return $task;
        }

        // This is the latest cycle's own due_date, i.e. when the *next* instance will be
        // generated (see GenerateRecurringTasksCommand), not that due_date plus the interval.
        $nextInstanceAt = $this->taskRepository->latestInstance($task->id)?->due_date;

        // No further instance will ever be generated once the latest cycle's due date has
        // reached or passed the recurrence end date.
        if ($nextInstanceAt !== null && $task->recurrence_end_date !== null && $nextInstanceAt->gte($task->recurrence_end_date)) {
            $nextInstanceAt = null;
        }

        $task->next_instance_at = $nextInstanceAt?->toIso8601String();

        return $task;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->taskRepository->paginate($filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(string $uuid, array $payload, Admin $actor, Request $request): CommunicationTask
    {
        $task = $this->find($uuid);

        $data = array_filter([
            'title' => $payload['title'] ?? null,
            'priority' => $payload['priority'] ?? null,
            'description' => $payload['description'] ?? null,
            'start_date' => $payload['start_date'] ?? null,
            'due_date' => $payload['due_date'] ?? null,
        ], fn ($value) => $value !== null);

        if (array_key_exists('assigned_to', $payload)) {
            $data['assigned_to'] = $this->resolveAssignee($payload['assigned_to'])->uuid;
        }

        foreach (['reminders_enabled', 'reminder_2_days_before', 'reminder_1_day_before', 'reminder_on_due_date'] as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = (bool) $payload[$field];
            }
        }

        if (array_key_exists('is_recurring', $payload)) {
            $isRecurring = (bool) $payload['is_recurring'];
            $repeatNonStop = $isRecurring && (bool) ($payload['repeat_non_stop'] ?? $task->repeat_non_stop);

            $data['is_recurring'] = $isRecurring;
            $data['recurrence_status'] = $isRecurring
                ? ($task->recurrence_status ?? TaskRecurrenceStatusEnum::ACTIVE->value)
                : null;
            $data['repeat_non_stop'] = $repeatNonStop;
            $data['recurrence_interval_value'] = $isRecurring ? ($payload['recurrence_interval_value'] ?? $task->recurrence_interval_value) : null;
            $data['recurrence_interval_unit'] = $isRecurring ? ($payload['recurrence_interval_unit'] ?? $task->recurrence_interval_unit) : null;
            $data['recurrence_end_date'] = ($isRecurring && ! $repeatNonStop) ? ($payload['recurrence_end_date'] ?? $task->recurrence_end_date) : null;
        } elseif ($task->is_recurring) {
            // `is_recurring` itself wasn't touched, but the task is already recurring: allow
            // adjusting its other recurrence settings independently.
            if (array_key_exists('repeat_non_stop', $payload)) {
                $data['repeat_non_stop'] = (bool) $payload['repeat_non_stop'];
            }
            if (array_key_exists('recurrence_interval_value', $payload)) {
                $data['recurrence_interval_value'] = $payload['recurrence_interval_value'];
            }
            if (array_key_exists('recurrence_interval_unit', $payload)) {
                $data['recurrence_interval_unit'] = $payload['recurrence_interval_unit'];
            }
            if (array_key_exists('recurrence_end_date', $payload)) {
                $data['recurrence_end_date'] = $payload['recurrence_end_date'];
            }
        }

        $task = $this->taskRepository->update($task, $data);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::COMMUNICATION_TASK_UPDATED,
            $request,
            $actor->uuid,
            ['task_uuid' => $task->uuid],
            $actor->displayName().' updated task: '.$task->title.'.',
            CommunicationTask::class,
            $task->uuid,
            ModuleEnums::communications,
            200,
        );

        return $this->findForDisplay($task->uuid);
    }

    public function delete(string $uuid, Admin $actor, Request $request): void
    {
        $task = $this->find($uuid);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::COMMUNICATION_TASK_DELETED,
            $request,
            $actor->uuid,
            ['task_uuid' => $task->uuid],
            $actor->displayName().' deleted task: '.$task->title.'.',
            CommunicationTask::class,
            $task->uuid,
            ModuleEnums::communications,
            200,
        );

        $this->taskRepository->delete($task);
    }

    public function markDone(string $uuid, Admin $actor, Request $request): CommunicationTask
    {
        $task = $this->find($uuid);
        $task = $this->taskRepository->update($task, ['status' => TaskStatusEnum::DONE->value]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::COMMUNICATION_TASK_MARKED_DONE,
            $request,
            $actor->uuid,
            ['task_uuid' => $task->uuid],
            $actor->displayName().' marked task as done: '.$task->title.'.',
            CommunicationTask::class,
            $task->uuid,
            ModuleEnums::communications,
            200,
        );

        return $this->findForDisplay($task->uuid);
    }

    public function pauseRecurrence(string $uuid, Admin $actor, Request $request): CommunicationTask
    {
        return $this->setRecurrenceStatus($uuid, TaskRecurrenceStatusEnum::PAUSED, AuditActionEnum::COMMUNICATION_TASK_RECURRENCE_PAUSED, 'paused', $actor, $request);
    }

    public function resumeRecurrence(string $uuid, Admin $actor, Request $request): CommunicationTask
    {
        return $this->setRecurrenceStatus($uuid, TaskRecurrenceStatusEnum::ACTIVE, AuditActionEnum::COMMUNICATION_TASK_RECURRENCE_RESUMED, 'resumed', $actor, $request);
    }

    public function disableRecurrence(string $uuid, Admin $actor, Request $request): CommunicationTask
    {
        return $this->setRecurrenceStatus($uuid, TaskRecurrenceStatusEnum::DISABLED, AuditActionEnum::COMMUNICATION_TASK_RECURRENCE_DISABLED, 'disabled', $actor, $request);
    }

    /**
     * Every generated instance of a recurring task (the root plus every child), for the
     * "Recurring History" panel. Works whether given the root task or one of its instances.
     *
     * @return Collection<int, CommunicationTask>
     */
    public function listInstances(string $uuid): Collection
    {
        $task = $this->find($uuid);

        return $this->taskRepository->listInstances($task->recurrenceRootId());
    }

    public function addNote(string $uuid, string $body, Admin $actor, Request $request): CommunicationTask
    {
        $task = $this->find($uuid);

        $this->noteRepository->create([
            'task_id' => $task->id,
            'author_id' => $actor->uuid,
            'body' => $body,
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::COMMUNICATION_TASK_NOTE_ADDED,
            $request,
            $actor->uuid,
            ['task_uuid' => $task->uuid],
            $actor->displayName().' added a note to task: '.$task->title.'.',
            CommunicationTask::class,
            $task->uuid,
            ModuleEnums::communications,
            201,
        );

        return $this->findForDisplay($task->uuid);
    }

    /**
     * @return array{total: int, upcoming: int, due_today: int, overdue: int}
     */
    public function overview(): array
    {
        return $this->taskRepository->overviewStats();
    }

    /**
     * Active admins for the "Assigned To" picker.
     *
     * @return Collection<int, Admin>
     */
    public function assignableAdmins(): Collection
    {
        return $this->adminRepository->listActive();
    }

    private function setRecurrenceStatus(
        string $uuid,
        TaskRecurrenceStatusEnum $status,
        AuditActionEnum $action,
        string $verb,
        Admin $actor,
        Request $request,
    ): CommunicationTask {
        $task = $this->find($uuid);

        if (! $task->is_recurring) {
            throw new ApiException('This task is not recurring.', 422);
        }

        // Recurrence only ever lives on the root; redirect a child instance to its parent.
        $target = $task->parent_task_id !== null ? $this->find($task->parentTask->uuid) : $task;
        $target = $this->taskRepository->update($target, ['recurrence_status' => $status->value]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            $action,
            $request,
            $actor->uuid,
            ['task_uuid' => $target->uuid],
            $actor->displayName().' '.$verb.' recurrence for task: '.$target->title.'.',
            CommunicationTask::class,
            $target->uuid,
            ModuleEnums::communications,
            200,
        );

        return $this->findForDisplay($target->uuid);
    }

    private function resolveAssignee(string $uuid): Admin
    {
        $admin = $this->adminRepository->findByUuid($uuid);

        if (! $admin instanceof Admin) {
            throw new ApiException('The selected assignee does not exist.', 422);
        }

        return $admin;
    }
}
