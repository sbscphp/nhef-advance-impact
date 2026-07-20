<?php

namespace App\Models;

use App\Enums\CampaignStatusEnum;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
            'starts_at' => 'date',
            'ends_at' => 'date',
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

    public function isOpenForDonations(): bool
    {
        if ($this->status !== CampaignStatusEnum::ACTIVE->value) {
            return false;
        }

        return $this->ends_at === null || ! $this->ends_at->isPast();
    }
}
