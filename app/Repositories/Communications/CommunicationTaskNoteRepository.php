<?php

namespace App\Repositories\Communications;

use App\Models\CommunicationTaskNote;
use App\Repositories\Contracts\Communications\CommunicationTaskNoteRepositoryInterface;
use Illuminate\Support\Collection;

class CommunicationTaskNoteRepository implements CommunicationTaskNoteRepositoryInterface
{
    public function create(array $data): CommunicationTaskNote
    {
        return CommunicationTaskNote::create($data);
    }

    public function listForTask(int $taskId): Collection
    {
        return CommunicationTaskNote::query()
            ->with(['author'])
            ->where('task_id', $taskId)
            ->latest()
            ->get();
    }
}
