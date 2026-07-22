<?php

namespace App\Repositories\Contracts\Event;

use App\Models\EventRegistration;
use Illuminate\Support\Collection;

interface EventRegistrationItemRepositoryInterface
{
    /**
     * @param  list<array<string, mixed>>  $items
     * @return Collection<int, \App\Models\EventRegistrationItem>
     */
    public function createMany(EventRegistration $registration, array $items): Collection;
}
