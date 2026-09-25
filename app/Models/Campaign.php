<?php

namespace App\Models;

use App\Enums\CampaignStatusEnum;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'goal_amount' => 'decimal:2',
            'raised_amount' => 'decimal:2',
            'allow_one_time' => 'boolean',
            'allow_recurring' => 'boolean',
            'allow_anonymous' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'timer_lead_hours' => 'integer',
        ];
    }

    public function pledges(): HasMany
    {
        return $this->hasMany(Pledge::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function allocatedAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'allocated_admin_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by', 'uuid')->withTrashed();
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(CampaignProject::class)->orderBy('sort_order');
    }

    public function campaignInstitutions(): HasMany
    {
        return $this->hasMany(CampaignInstitution::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CampaignStatusEnum::ACTIVE->value);
    }

    public function progressPercentage(): int
    {
        if ((float) $this->goal_amount <= 0) {
            return 0;
        }

        return (int) min(100, round(((float) $this->raised_amount / (float) $this->goal_amount) * 100));
    }

    /**
     * Drives the public countdown: the window before start in which the timer is shown
     * ("countdown"), then "live" until the end, then "ended". The client ticks down from
     * seconds_until_start / seconds_remaining using server_time to avoid clock skew.
     *
     * @return array<string, mixed>
     */
    public function timer(): array
    {
        $now = now();
        $leadHours = $this->timer_lead_hours ?? (int) config('campaigns.default_timer_lead_hours');
        $countdownStartsAt = $this->starts_at?->copy()->subHours($leadHours);

        $state = match (true) {
            $this->status === CampaignStatusEnum::PAUSED->value => 'paused',
            $this->ends_at !== null && $now->greaterThan($this->ends_at) => 'ended',
            $this->starts_at !== null && $now->lessThan($this->starts_at) => $now->greaterThanOrEqualTo($countdownStartsAt) ? 'countdown' : 'upcoming',
            default => 'live',
        };

        return [
            'state' => $state,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'lead_hours' => $leadHours,
            'countdown_starts_at' => $countdownStartsAt?->toIso8601String(),
            'server_time' => $now->toIso8601String(),
            'seconds_until_start' => $this->starts_at !== null && $now->lessThan($this->starts_at) ? (int) $now->diffInSeconds($this->starts_at) : 0,
            'seconds_remaining' => $this->ends_at !== null && $now->lessThan($this->ends_at) ? (int) $now->diffInSeconds($this->ends_at) : 0,
        ];
    }

    /** What the admin list shows in its Status column ("ongoing" / "closed" / ...), derived from status plus the schedule. */
    public function displayStatus(): string
    {
        return match (true) {
            $this->status === CampaignStatusEnum::DRAFT->value => 'draft',
            $this->status === CampaignStatusEnum::PAUSED->value => 'paused',
            $this->status !== CampaignStatusEnum::ACTIVE->value => 'closed',
            $this->ends_at !== null && $this->ends_at->isPast() => 'closed',
            $this->starts_at !== null && $this->starts_at->isFuture() => 'upcoming',
            default => 'ongoing',
        };
    }

    public function isOpenForDonations(): bool
    {
        if ($this->status !== CampaignStatusEnum::ACTIVE->value) {
            return false;
        }

        return $this->ends_at === null || ! $this->ends_at->isPast();
    }
}
