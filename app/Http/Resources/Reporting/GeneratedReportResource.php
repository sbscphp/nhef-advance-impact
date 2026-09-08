<?php

namespace App\Http\Resources\Reporting;

use App\Enums\ReportDatasetEnum;
use App\Models\GeneratedReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GeneratedReport */
class GeneratedReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'dataset' => $this->dataset,
            'dataset_label' => ReportDatasetEnum::from($this->dataset)->label(),
            'fields' => $this->fields,
            'period' => $this->period,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator === null ? null : [
                'uuid' => $this->creator->uuid,
                'name' => $this->creator->displayName(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
