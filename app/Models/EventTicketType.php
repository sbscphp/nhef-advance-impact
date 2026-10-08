<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTicketType extends Model
{
    use BelongsToTenant, HasUuid;

    public static function constrainToTenant(Builder $query, Institution $tenant): void
    {
        $query->whereIn('event_ticket_types.event_id', Event::query()->select('events.id'));
    }

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sales_close_at' => 'datetime',
            'discount_percentage' => 'decimal:2',
            'discount_starts_at' => 'datetime',
            'discount_ends_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function quantityRemaining(): ?int
    {
        if ($this->quantity === null) {
            return null;
        }

        return max(0, (int) $this->quantity - (int) $this->quantity_sold);
    }

    public function isAvailable(): bool
    {
        if ($this->sales_close_at !== null && $this->sales_close_at->isPast()) {
            return false;
        }

        $remaining = $this->quantityRemaining();

        return $remaining === null || $remaining > 0;
    }
}
