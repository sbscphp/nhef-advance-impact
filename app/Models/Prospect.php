<?php

namespace App\Models;

use App\Enums\ProspectPipelineStageEnum;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prospect extends Model
{
    use HasFactory, HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:2',
            'stage_entered_at' => 'datetime',
        ];
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_admin_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by', 'uuid');
    }

    public function callLogs(): HasMany
    {
        return $this->hasMany(ProspectCallLog::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(ProspectInvite::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(ProspectProposal::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ProspectMessage::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function daysInCurrentStage(): int
    {
        if (! $this->stage_entered_at) {
            return 0;
        }

        return max(0, (int) $this->stage_entered_at->startOfDay()->diffInDays(now()->startOfDay(), false));
    }

    /**
     * Drives the pipeline stepper: one row per {@see ProspectPipelineStageEnum}, flagged
     * completed/current relative to this prospect's current stage.
     *
     * @return list<array{stage: string, label: string, completed: bool, current: bool}>
     */
    public function pipelineSteps(): array
    {
        $currentOrder = ProspectPipelineStageEnum::from($this->stage)->order();

        return array_map(function (ProspectPipelineStageEnum $step) use ($currentOrder) {
            return [
                'stage' => $step->value,
                'label' => $step->label(),
                'completed' => $step->order() < $currentOrder,
                'current' => $step->order() === $currentOrder,
            ];
        }, ProspectPipelineStageEnum::cases());
    }
}
