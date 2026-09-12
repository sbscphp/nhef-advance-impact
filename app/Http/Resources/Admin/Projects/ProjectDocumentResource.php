<?php

namespace App\Http\Resources\Admin\Projects;

use App\Enums\ProjectDocumentCategoryEnum;
use App\Models\ProjectDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProjectDocument */
class ProjectDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'category' => $this->category,
            'category_label' => $this->category !== null ? ProjectDocumentCategoryEnum::from($this->category)->label() : null,
            'file_url' => $this->file_url,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator === null ? null : [
                'uuid' => $this->creator->uuid,
                'name' => $this->creator->displayName(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
