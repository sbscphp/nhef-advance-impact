<?php

namespace App\Http\Requests\Guest\Events;

use App\Http\Requests\Customer\Events\RegisterForEventRequest;

/**
 * Same shape as the authenticated RegisterForEventRequest, plus the attendee identity fields a
 * guest has to supply instead of an account (BRD ENT-04: register without an account).
 */
class GuestRegisterForEventRequest extends RegisterForEventRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
        ]);
    }
}
