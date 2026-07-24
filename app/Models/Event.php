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

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', EventStatusEnum::PUBLISHED->value);
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
     * Lowest ticket price among loaded ticketTypes, or null if none exist. Used to render
     * "Free" vs a "Starting from" price on the event listing/detail without persisting a
     * redundant price on the event itself.
     */
    public function lowestTicketPrice(): ?string
    {
        if (! $this->relationLoaded('ticketTypes') || $this->ticketTypes->isEmpty()) {
            return null;
        }

        return (string) $this->ticketTypes->min('price');
    }
}
