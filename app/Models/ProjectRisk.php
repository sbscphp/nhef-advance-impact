<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProjectRisk extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'all_team_members' => 'boolean',
            'resolved_at' => 'datetime',
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

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(Admin::class, 'project_risk_recipients', 'risk_id', 'admin_id')->withTimestamps();
    }

    public function raisedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'raised_by', 'uuid');
    }

    public function resolvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'resolved_by', 'uuid');
    }
}
