<?php

namespace App\Http\Resources\Admin\Projects;

use App\Models\ProjectExpenditure;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProjectExpenditure */
class ProjectExpenditureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'amount' => (string) $this->amount,
            'amount_formatted' => Money::format($this->amount, 'NGN'),
            'transaction_date' => $this->transaction_date?->toDateString(),
            'reference_id' => $this->reference_id,
            'evidence_url' => $this->evidence_url,
            'budget_line' => $this->whenLoaded('budgetLine', fn () => $this->budgetLine === null ? null : [
                'uuid' => $this->budgetLine->uuid,
                'title' => $this->budgetLine->title,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
