<?php

namespace App\Http\Resources\Admin\ConstituentManagement;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class ConstituentDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'code' => $this->code(),
            'first_name' => $this->firstname,
            'last_name' => $this->lastname,
            'name' => $this->displayName(),
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'invite_message' => $this->invite_message,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'date_joined' => $this->created_at?->toIso8601String(),
            'date_onboarded' => $this->onboarded_at?->toIso8601String(),
            'invited_at' => $this->invited_at?->toIso8601String(),
        ];
    }
}
