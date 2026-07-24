<?php

namespace App\Http\Resources\Settings;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentMethod */
class PaymentMethodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'brand' => $this->brand,
            'last_four' => $this->last_four,
            'exp_month' => $this->exp_month,
            'exp_year' => $this->exp_year,
            'expires_formatted' => $this->expiresFormatted(),
            'bank' => $this->bank,
            'is_default' => (bool) $this->is_default,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function expiresFormatted(): ?string
    {
        if ($this->exp_month === null || $this->exp_year === null) {
            return null;
        }

        return str_pad((string) $this->exp_month, 2, '0', STR_PAD_LEFT).'/'.substr((string) $this->exp_year, -2);
    }
}
