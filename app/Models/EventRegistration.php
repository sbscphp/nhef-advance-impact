<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventRegistration extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(EventRegistrationItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(EventRegistrationPayment::class);
    }

    /** True for a guest registration (BRD ENT-04); no user_id. */
    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public function attendeeName(): string
    {
        return $this->isGuest() ? (string) $this->guest_name : $this->user->displayName();
    }

    public function attendeeEmail(): string
    {
        return $this->isGuest() ? (string) $this->guest_email : (string) $this->user->email;
    }
}
