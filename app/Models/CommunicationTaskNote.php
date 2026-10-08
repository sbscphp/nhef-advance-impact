<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationTaskNote extends Model
{
    use BelongsToTenant, HasFactory, HasUuid;

    public static function constrainToTenant(Builder $query, Institution $tenant): void
    {
        $query->whereIn('communication_task_notes.task_id', CommunicationTask::query()->select('communication_tasks.id'));
    }

    protected $guarded = ['id', 'uuid'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(CommunicationTask::class, 'task_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'author_id', 'uuid')->withTrashed();
    }
}
