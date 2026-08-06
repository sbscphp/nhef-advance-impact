<?php

namespace App\Http\Requests\Customer\Events;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class RegisterForEventRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tickets' => ['required', 'array', 'min:1'],
            'tickets.*.ticket_type_uuid' => ['required', 'uuid', Rule::exists('event_ticket_types', 'uuid')],
            'tickets.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
