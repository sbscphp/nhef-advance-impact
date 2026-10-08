<?php

namespace App\Models;

use App\Enums\ProspectPipelineStageEnum;
use App\Models\Concerns\OwnedByInstitution;
use App\Traits\HasUuid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prospect extends Model
{
    use HasFactory, HasUuid, OwnedByInstitution;

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
        return $this->belongsTo(Admin::class, 'assigned_admin_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by', 'uuid')->withTrashed();
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

    public function stageHistory(): HasMany
    {
        return $this->hasMany(ProspectStageHistory::class);
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

        return self::daysBetween($this->stage_entered_at, now());
    }

    private static function daysBetween(CarbonInterface $start, CarbonInterface $end): int
    {
        return max(0, (int) $start->startOfDay()->diffInDays($end->copy()->startOfDay(), false));
    }

    /**
     * Drives the pipeline stepper: one row per {@see ProspectPipelineStageEnum}, flagged
     * completed/current relative to this prospect's current stage, each carrying when it was
     * entered, when it was left (null if it's the current, still-open stage), and how many days
     * were spent in it. A stage not yet reached has all three as null. If a stage was visited more
     * than once (moved back and forward), the most recent visit is what's shown.
     *
     * @return list<array{stage: string, label: string, completed: bool, current: bool, entered_at: ?string, completed_at: ?string, days_in_stage: ?int}>
     */
    public function pipelineSteps(): array
    {
        $currentOrder = ProspectPipelineStageEnum::from($this->stage)->order();

        $history = $this->relationLoaded('stageHistory') ? $this->stageHistory : $this->stageHistory()->get();
        $latestByStage = $history
            ->sortBy('entered_at')
            ->keyBy(fn (ProspectStageHistory $row) => $row->stage);

        return array_map(function (ProspectPipelineStageEnum $step) use ($currentOrder, $latestByStage) {
            $row = $latestByStage->get($step->value);
            $enteredAt = $row?->entered_at;
            $completedAt = $row?->exited_at;

            return [
                'stage' => $step->value,
                'label' => $step->label(),
                'completed' => $step->order() < $currentOrder,
                'current' => $step->order() === $currentOrder,
                'entered_at' => $enteredAt?->toIso8601String(),
                'completed_at' => $completedAt?->toIso8601String(),
                'days_in_stage' => $enteredAt ? self::daysBetween($enteredAt, $completedAt ?? now()) : null,
            ];
        }, ProspectPipelineStageEnum::cases());
    }
}
