<?php

namespace App\Http\Resources\Admin\Projects;

use App\Models\ProjectImpactReport;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProjectImpactReport */
class ProjectImpactReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'report_date' => $this->report_date?->toDateString(),
            'description' => $this->description,
            'expenditure_value' => $this->expenditure_value !== null ? (string) $this->expenditure_value : null,
            'expenditure_value_formatted' => $this->expenditure_value !== null ? Money::format($this->expenditure_value, 'NGN') : null,
            'evidence_urls' => $this->evidence_urls ?? [],
            'deliverable' => $this->whenLoaded('deliverable', fn () => $this->deliverable === null ? null : [
                'uuid' => $this->deliverable->uuid,
                'title' => $this->deliverable->title,
            ]),
            'budget_line' => $this->whenLoaded('budgetLine', fn () => $this->budgetLine === null ? null : [
                'uuid' => $this->budgetLine->uuid,
                'title' => $this->budgetLine->title,
            ]),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator === null ? null : [
                'uuid' => $this->creator->uuid,
                'name' => $this->creator->displayName(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
