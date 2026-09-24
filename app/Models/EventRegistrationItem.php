<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRegistrationItem extends Model
{
    use BelongsToTenant, HasUuid;

    public static function constrainToTenant(Builder $query, Institution $tenant): void
    {
        $query->whereIn('event_registration_items.event_registration_id', EventRegistration::query()->select('event_registrations.id'));
    }

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(EventTicketType::class, 'event_ticket_type_id');
    }
}
