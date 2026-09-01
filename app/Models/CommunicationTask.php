<?php

namespace App\Models;

use App\Enums\TaskStatusEnum;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class CommunicationTask extends Model
{
    use HasFactory, HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'recurrence_end_date' => 'date',
            'is_recurring' => 'boolean',
            'reminder_2_days_before' => 'boolean',
            'reminder_1_day_before' => 'boolean',
            'reminder_on_due_date' => 'boolean',
            'reminder_2_days_sent_at' => 'datetime',
            'reminder_1_day_sent_at' => 'datetime',
            'reminder_on_due_sent_at' => 'datetime',
        ];
    }

    public function callLog(): BelongsTo
    {
        return $this->belongsTo(CommunicationCallLog::class, 'call_log_id');
    }

    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_task_id');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(self::class, 'parent_task_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_to', 'uuid');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by', 'uuid');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CommunicationTaskNote::class, 'task_id')->latest();
    }

    /** The recurring root: this task itself if it's the original, otherwise its parent. */
    public function recurrenceRootId(): int
    {
        return $this->parent_task_id ?? $this->id;
    }

    /** upcoming/due_today/overdue/done, computed at read time, never stored. */
    public function computedView(): string
    {
        if ($this->status === TaskStatusEnum::DONE->value) {
            return 'done';
        }

        $today = Carbon::today();

        if ($this->due_date?->lt($today)) {
            return 'overdue';
        }

        if ($this->due_date?->isSameDay($today)) {
            return 'due_today';
        }

        return 'upcoming';
    }
}
