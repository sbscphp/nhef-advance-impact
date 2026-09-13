<?php

namespace App\Models;

use App\Enums\ResearchStatusEnum;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Research extends Model
{
    use HasUuid;

    protected $table = 'researches';

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'due_at' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function objectives(): HasMany
    {
        return $this->hasMany(ResearchObjective::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ResearchMilestone::class);
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(ResearchDeliverable::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by', 'uuid');
    }

    public function scopeWhereStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function isCompleted(): bool
    {
        return $this->status === ResearchStatusEnum::COMPLETED->value;
    }
}
