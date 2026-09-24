<?php

namespace App\Models;

use App\Enums\MentorshipMatchStatusEnum;
use App\Models\Concerns\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MentorshipMatch extends Model
{
    use BelongsToTenant, HasUuid;

    public static function constrainToTenant(Builder $query, Institution $tenant): void
    {
        $query->where(fn (Builder $inner) => $inner
            ->whereIn('mentorship_matches.mentor_profile_id', MentorProfile::query()->select('mentor_profiles.id'))
            ->orWhereIn('mentorship_matches.mentee_profile_id', MenteeProfile::query()->select('mentee_profiles.id')));
    }

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'matched_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function mentorProfile(): BelongsTo
    {
        return $this->belongsTo(MentorProfile::class);
    }

    public function menteeProfile(): BelongsTo
    {
        return $this->belongsTo(MenteeProfile::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(MentorshipReview::class);
    }

    public function isActive(): bool
    {
        return $this->status === MentorshipMatchStatusEnum::ACTIVE->value;
    }
}
