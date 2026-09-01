<?php

namespace App\Repositories\Communications;

use App\Enums\TaskStatusEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\CommunicationCallLog;
use App\Repositories\Contracts\Communications\CommunicationCallLogRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class CommunicationCallLogRepository implements CommunicationCallLogRepositoryInterface
{
    public function create(array $data): CommunicationCallLog
    {
        return CommunicationCallLog::create($data);
    }

    public function findByUuid(string $uuid): ?CommunicationCallLog
    {
        return CommunicationCallLog::query()
            ->with(['contact', 'logger', 'followUpTasks.assignee', 'followUpTasks.callLog.contact', 'followUpTasks.notes.author'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = CommunicationCallLog::query()
            ->with(['contact', 'logger', 'followUpTasks.assignee', 'followUpTasks.callLog.contact', 'followUpTasks.notes.author'])
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where(function ($query) use ($filters) {
                    $query->where('purpose', 'like', '%'.$filters['search'].'%')
                        ->orWhereHas('contact', fn ($query) => $query
                            ->where('firstname', 'like', '%'.$filters['search'].'%')
                            ->orWhere('lastname', 'like', '%'.$filters['search'].'%'));
                })
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'call_date');
        ListingFilterRules::applySort($query, $filters, [], 'call_date');

        return $query->paginate($perPage);
    }

    public function count(): int
    {
        return CommunicationCallLog::query()->count();
    }

    public function overviewStats(): array
    {
        $today = Carbon::today();

        $countWithFollowUp = fn (callable $constrain) => (int) CommunicationCallLog::query()
            ->whereHas('followUpTasks', fn ($query) => $constrain($query->where('status', TaskStatusEnum::PENDING->value)))
            ->count();

        return [
            'total' => $this->count(),
            'upcoming' => $countWithFollowUp(fn ($query) => $query->where('due_date', '>', $today)),
            'due_today' => $countWithFollowUp(fn ($query) => $query->whereDate('due_date', $today)),
            'overdue' => $countWithFollowUp(fn ($query) => $query->where('due_date', '<', $today)),
        ];
    }
}
