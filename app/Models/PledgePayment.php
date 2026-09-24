<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PledgePayment extends Model
{
    use BelongsToTenant, HasUuid;

    public static function constrainToTenant(Builder $query, Institution $tenant): void
    {
        $query->whereIn('pledge_payments.pledge_id', Pledge::query()->select('pledges.id'));
    }

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function pledge(): BelongsTo
    {
        return $this->belongsTo(Pledge::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(PledgeInstallment::class, 'pledge_installment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
