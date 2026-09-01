<?php

namespace App\Repositories\Contracts\Communications;

use App\Models\CommunicationTaskNote;
use Illuminate\Support\Collection;

interface CommunicationTaskNoteRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CommunicationTaskNote;

    /**
     * @return Collection<int, CommunicationTaskNote>
     */
    public function listForTask(int $taskId): Collection;
}
