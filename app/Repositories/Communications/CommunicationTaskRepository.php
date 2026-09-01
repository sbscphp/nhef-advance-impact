<?php

namespace App\Repositories\Communications;

use App\Enums\TaskStatusEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\CommunicationTask;
use App\Repositories\Contracts\Communications\CommunicationTaskRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CommunicationTaskRepository implements CommunicationTaskRepositoryInterface
{
    public function create(array $data): CommunicationTask
    {
        return CommunicationTask::create($data);
    }

    public function findByUuid(string $uuid): ?CommunicationTask
    {
        return CommunicationTask::query()
            ->with(['callLog.contact', 'assignee', 'creator', 'notes.author'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function update(CommunicationTask $task, array $data): CommunicationTask
    {
        $task->forceFill($data)->save();

        return $task->refresh();
    }

    public function delete(CommunicationTask $task): void
    {
        $task->delete();
    }

    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $today = Carbon::today();

        $query = CommunicationTask::query()
            ->with(['callLog.contact', 'assignee', 'notes.author'])
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%')
            )
            ->when(
                filled($filters['filters']['priority'] ?? null),
                fn ($query) => $query->where('priority', $filters['filters']['priority'])
            )
            ->when(
                filled($filters['filters']['assigned_to'] ?? null),
                fn ($query) => $query->whereHas('assignee', fn ($query) => $query->where('uuid', $filters['filters']['assigned_to']))
            )
            ->when(
                filled($filters['filters']['view'] ?? null),
                function ($query) use ($filters, $today) {
                    match ($filters['filters']['view']) {
                        'done' => $query->where('status', TaskStatusEnum::DONE->value),
                        'overdue' => $query->where('status', TaskStatusEnum::PENDING->value)->where('due_date', '<', $today),
                        'due_today' => $query->where('status', TaskStatusEnum::PENDING->value)->whereDate('due_date', $today),
                        'upcoming' => $query->where('status', TaskStatusEnum::PENDING->value)->where('due_date', '>', $today),
                        default => null,
                    };
                }
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'due_date');
        ListingFilterRules::applySort($query, $filters, [
            'due_date' => fn ($query, string $direction) => $query->orderBy('due_date', $direction),
        ], 'created_at');

        return $query->paginate($perPage);
    }

    public function listInstances(int $rootTaskId): Collection
    {
        return CommunicationTask::query()
            ->with(['assignee'])
            ->where(fn ($query) => $query->where('id', $rootTaskId)->orWhere('parent_task_id', $rootTaskId))
            ->orderByDesc('due_date')
            ->get();
    }

    public function latestInstance(int $rootTaskId): ?CommunicationTask
    {
        return $this->listInstances($rootTaskId)->first();
    }

    public function overviewStats(?int $callLogId = null): array
    {
        $today = Carbon::today();

        $scoped = fn () => CommunicationTask::query()
            ->when($callLogId !== null, fn ($query) => $query->where('call_log_id', $callLogId));

        return [
            'total' => (int) $scoped()->count(),
            'upcoming' => (int) $scoped()->where('status', TaskStatusEnum::PENDING->value)->where('due_date', '>', $today)->count(),
            'due_today' => (int) $scoped()->where('status', TaskStatusEnum::PENDING->value)->whereDate('due_date', $today)->count(),
            'overdue' => (int) $scoped()->where('status', TaskStatusEnum::PENDING->value)->where('due_date', '<', $today)->count(),
        ];
    }
}
