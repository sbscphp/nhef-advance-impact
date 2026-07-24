<?php

namespace App\Models;

use App\Enums\DonationFrequencyEnum;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Donation extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'total_received' => 'decimal:2',
            'is_anonymous' => 'boolean',
            'next_charge_at' => 'date',
            'completed_at' => 'datetime',
            'paused_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DonationPayment::class);
    }

    public function isRecurring(): bool
    {
        return DonationFrequencyEnum::from($this->frequency)->isRecurring();
    }

    /** True for a guest donation (BRD ENT-04: donate without an account); no user_id. */
    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public function donorName(): string
    {
        return $this->isGuest() ? (string) $this->guest_name : $this->user->displayName();
    }

    public function donorEmail(): string
    {
        return $this->isGuest() ? (string) $this->guest_email : (string) $this->user->email;
    }
}
