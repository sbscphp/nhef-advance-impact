<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventWaitlistEntry extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'projected_value' => 'decimal:2',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(EventTicketType::class, 'event_ticket_type_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** True for a guest waitlist entry; no user_id. */
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
