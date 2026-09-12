<?php

namespace App\Http\Resources\Admin\Projects;

use App\Models\ProjectBudgetLine;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProjectBudgetLine
 *
 * Expects the controller to have attached `amount_utilized`, `remaining_amount`, and
 * `percent_remaining` transient attributes via {@see \App\Services\Project\ProjectService::budgetLineSummary()}.
 */
class ProjectBudgetLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = 'NGN';

        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'reference_id' => $this->reference_id,
            'amount_allocated' => (string) $this->amount_allocated,
            'amount_allocated_formatted' => Money::format($this->amount_allocated, $currency),
            'amount_utilized' => (string) ($this->amount_utilized ?? '0'),
            'amount_utilized_formatted' => Money::format($this->amount_utilized ?? '0', $currency),
            'remaining_amount' => (string) ($this->remaining_amount ?? $this->amount_allocated),
            'remaining_amount_formatted' => Money::format($this->remaining_amount ?? $this->amount_allocated, $currency),
            'percent_remaining' => $this->percent_remaining ?? 100.0,
            'starts_at' => $this->starts_at?->toDateString(),
            'due_at' => $this->due_at?->toDateString(),
            'all_team_members' => (bool) $this->all_team_members,
            'recipients' => $this->whenLoaded('recipients', fn () => $this->recipients->map(fn ($admin) => [
                'uuid' => $admin->uuid,
                'name' => $admin->displayName(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
