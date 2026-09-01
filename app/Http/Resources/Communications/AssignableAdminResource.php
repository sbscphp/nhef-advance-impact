<?php

namespace App\Http\Resources\Communications;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Admin */
class AssignableAdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->displayName(),
            'email' => $this->email,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
