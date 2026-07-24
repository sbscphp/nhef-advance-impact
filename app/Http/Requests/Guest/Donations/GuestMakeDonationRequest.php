<?php

namespace App\Http\Requests\Guest\Donations;

use App\Http\Requests\Customer\Donations\MakeDonationRequest;

/**
 * Same shape as the authenticated MakeDonationRequest, plus the donor identity fields a guest
 * has to supply instead of an account (BRD ENT-04: donate without registering).
 */
class GuestMakeDonationRequest extends MakeDonationRequest
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
