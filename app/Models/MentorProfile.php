<?php

namespace App\Models;

use App\Enums\MentorListingStatusEnum;
use App\Enums\MentorReviewStatusEnum;
use App\Models\Concerns\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MentorProfile extends Model
{
    use BelongsToTenant, HasUuid;

    public static function constrainToTenant(Builder $query, Institution $tenant): void
    {
        $query->whereIn('mentor_profiles.user_id', User::query()->select('users.id'));
    }

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'expertise_tags' => 'array',
            'guidance_areas' => 'array',
            'available_days' => 'array',
            'socials' => 'array',
            'eligibility_confirmed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by')->withTrashed();
    }

    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'suspended_by')->withTrashed();
    }

    public function matches(): HasMany
    {
        return $this->hasMany(MentorshipMatch::class);
    }

    /** Eligible to receive a new auto-matched mentee: approved, not paused, and under capacity. */
    public function isAvailableForMatching(): bool
    {
        return $this->review_status === MentorReviewStatusEnum::APPROVED->value
            && $this->listing_status === MentorListingStatusEnum::ACTIVE->value
            && (int) $this->current_mentee_count < (int) $this->max_capacity;
    }
}
