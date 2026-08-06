<?php

namespace App\Http\Resources\Admin;

use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BankAccount */
class BankAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'bank_account_id' => $this->uuid,
            'account_number' => $this->account_number,
            'account_name' => $this->account_name,
            'bank' => BankResource::make($this->whenLoaded('bank')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
