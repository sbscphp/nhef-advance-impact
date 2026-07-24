<?php

namespace App\Models;

use App\Enums\MentorshipMatchStatusEnum;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MentorshipMatch extends Model
{
    use HasUuid;

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
