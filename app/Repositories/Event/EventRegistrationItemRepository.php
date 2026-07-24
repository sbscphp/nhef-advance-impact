<?php

namespace App\Repositories\Event;

use App\Models\EventRegistration;
use App\Models\EventRegistrationItem;
use App\Repositories\Contracts\Event\EventRegistrationItemRepositoryInterface;
use Illuminate\Support\Collection;

class EventRegistrationItemRepository implements EventRegistrationItemRepositoryInterface
{
    public function createMany(EventRegistration $registration, array $items): Collection
    {
        return collect($items)->map(function (array $item) use ($registration): EventRegistrationItem {
            return EventRegistrationItem::create([
                'event_registration_id' => $registration->id,
                ...$item,
            ]);
        });
    }
}
