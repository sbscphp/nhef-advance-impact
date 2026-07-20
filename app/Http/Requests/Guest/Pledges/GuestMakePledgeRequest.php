<?php

namespace App\Http\Requests\Guest\Pledges;

use App\Http\Requests\Customer\Pledges\MakePledgeRequest;

/**
 * Same shape as the authenticated MakePledgeRequest, plus the donor identity fields a guest
 * has to supply instead of an account (BRD ENT-04: donate without registering).
 */
class GuestMakePledgeRequest extends MakePledgeRequest
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
