<?php

namespace App\Models;

use App\Enums\EventStatusEnum;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_ends_at' => 'datetime',
            'waitlist_enabled' => 'boolean',
        ];
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(EventTicketType::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(EventWaitlistEntry::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', EventStatusEnum::PUBLISHED->value);
    }

    /** Query equivalent of {@see self::displayStatus()}: derives scheduled/ongoing/completed for published events from starts_at/ends_at. */
    public function scopeWhereDisplayStatus(Builder $query, string $displayStatus): Builder
    {
        $now = now();

        return match ($displayStatus) {
            'scheduled' => $query->where('status', EventStatusEnum::PUBLISHED->value)
                ->where('starts_at', '>', $now),
            'ongoing' => $query->where('status', EventStatusEnum::PUBLISHED->value)
                ->where('starts_at', '<=', $now)
                ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now)),
            'completed' => $query->where('status', EventStatusEnum::PUBLISHED->value)
                ->whereNotNull('ends_at')
                ->where('ends_at', '<', $now),
            default => $query->where('status', $displayStatus),
        };
    }

    /**
     * Admin-facing status label combining `status` with the derived {@see self::timelineStatus()}.
     *
     * @return 'draft'|'scheduled'|'ongoing'|'completed'|'cancelled'|'deactivated'|'archived'
     */
    public function displayStatus(): string
    {
        return match ($this->status) {
            EventStatusEnum::DRAFT->value => 'draft',
            EventStatusEnum::CANCELLED->value => 'cancelled',
            EventStatusEnum::DEACTIVATED->value => 'deactivated',
            EventStatusEnum::ARCHIVED->value => 'archived',
            default => match ($this->timelineStatus()) {
                'upcoming' => 'scheduled',
                'ended' => 'completed',
                default => 'ongoing',
            },
        };
    }

    public function isRegistrationOpen(): bool
    {
        if ($this->status !== EventStatusEnum::PUBLISHED->value) {
            return false;
        }

        if ($this->registration_ends_at !== null && $this->registration_ends_at->isPast()) {
            return false;
        }

        return $this->ends_at === null || ! $this->ends_at->isPast();
    }

    /**
     * @return 'upcoming'|'ongoing'|'ended'
     */
    public function timelineStatus(): string
    {
        $now = now();

        if ($this->starts_at->isFuture()) {
            return 'upcoming';
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return 'ended';
        }

        return 'ongoing';
    }

    public function seatsRemaining(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        return max(0, (int) $this->capacity - (int) $this->seats_taken);
    }

    /**
     * Lowest ticket price among loaded ticketTypes (null if none), for rendering "Free" vs
     * "Starting from" without persisting a redundant price on the event itself.
     */
    public function lowestTicketPrice(): ?string
    {
        if (! $this->relationLoaded('ticketTypes') || $this->ticketTypes->isEmpty()) {
            return null;
        }

        return (string) $this->ticketTypes->min('price');
    }
}
