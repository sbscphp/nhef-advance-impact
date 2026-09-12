<?php

namespace App\Models;

use App\Enums\ProjectStatusEnum;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'approved_budget' => 'decimal:2',
            'funding_received' => 'decimal:2',
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'funded_by_donor' => 'boolean',
            'funded_by_donation' => 'boolean',
            'archived_at' => 'datetime',
            'on_hold_at' => 'datetime',
        ];
    }

    public function teamMembers(): BelongsToMany
    {
        return $this->belongsToMany(Admin::class, 'project_team_members')
            ->withPivot('role_title', 'is_manager')
            ->withTimestamps();
    }

    public function managers(): BelongsToMany
    {
        return $this->teamMembers()->wherePivot('is_manager', true);
    }

    public function fundingDonors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_funding_donors')->withTimestamps();
    }

    public function fundingCampaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'project_funding_campaigns')->withTimestamps();
    }

    public function objectives(): HasMany
    {
        return $this->hasMany(ProjectObjective::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class);
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(ProjectDeliverable::class);
    }

    public function budgetLines(): HasMany
    {
        return $this->hasMany(ProjectBudgetLine::class);
    }

    public function expenditures(): HasMany
    {
        return $this->hasMany(ProjectExpenditure::class);
    }

    public function impactReports(): HasMany
    {
        return $this->hasMany(ProjectImpactReport::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

    public function broadcasts(): HasMany
    {
        return $this->hasMany(ProjectBroadcast::class);
    }

    public function risks(): HasMany
    {
        return $this->hasMany(ProjectRisk::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by', 'uuid');
    }

    public function scopeWhereStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function isActive(): bool
    {
        return $this->status === ProjectStatusEnum::ACTIVE->value;
    }
}
