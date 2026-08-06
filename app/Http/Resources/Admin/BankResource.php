<?php

namespace App\Http\Resources\Admin;

use App\Models\Bank;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Bank */
class BankResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'bank_id' => $this->uuid,
            'name' => $this->name,
            'code' => $this->code,
        ];
    }
}
