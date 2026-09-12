<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProjectDeliverable extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'due_at' => 'date',
            'all_team_members' => 'boolean',
            'notify_all_team_members' => 'boolean',
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class, 'milestone_id');
    }

    public function assignments(): MorphMany
    {
        return $this->morphMany(ProjectAssignment::class, 'assignable');
    }

    public function notifyRecipients(): BelongsToMany
    {
        return $this->belongsToMany(Admin::class, 'project_deliverable_notify_recipients', 'deliverable_id', 'admin_id')->withTimestamps();
    }

    /** completed | overdue | pending */
    public function status(): string
    {
        if ($this->is_completed) {
            return 'completed';
        }

        return $this->due_at !== null && $this->due_at->isPast() ? 'overdue' : 'pending';
    }

    public function daysOverdue(): ?int
    {
        if ($this->is_completed || $this->due_at === null || ! $this->due_at->isPast()) {
            return null;
        }

        return (int) $this->due_at->diffInDays(now());
    }
}
