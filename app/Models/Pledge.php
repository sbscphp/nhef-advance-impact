<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pledge extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'is_anonymous' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'next_installment_due_at' => 'date',
            'completed_at' => 'datetime',
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

    public function installments(): HasMany
    {
        return $this->hasMany(PledgeInstallment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PledgePayment::class);
    }

    public function amountRemaining(): string
    {
        return number_format((float) $this->total_amount - (float) $this->amount_paid, 2, '.', '');
    }

    public function progressPercentage(): int
    {
        if ((float) $this->total_amount <= 0) {
            return 0;
        }

        return (int) min(100, round(((float) $this->amount_paid / (float) $this->total_amount) * 100));
    }
}
