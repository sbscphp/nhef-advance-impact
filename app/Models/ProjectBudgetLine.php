<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectBudgetLine extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'amount_allocated' => 'decimal:2',
            'starts_at' => 'date',
            'due_at' => 'date',
            'all_team_members' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function expenditures(): HasMany
    {
        return $this->hasMany(ProjectExpenditure::class, 'budget_line_id');
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(Admin::class, 'project_budget_line_recipients', 'budget_line_id', 'admin_id')->withTimestamps();
    }
}
