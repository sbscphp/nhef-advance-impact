<?php

namespace App\Models;

use App\Enums\MentorshipMatchStatusEnum;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MenteeProfile extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'interest_areas' => 'array',
            'skills' => 'array',
            'available_days' => 'array',
            'socials' => 'array',
            'consent_confirmed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(MentorshipMatch::class);
    }

    /** At most one active match at a time (app-level rule enforced by MentorMatchingService). */
    public function activeMatch(): HasOne
    {
        return $this->hasOne(MentorshipMatch::class)->where('status', MentorshipMatchStatusEnum::ACTIVE->value);
    }
}
