<?php

namespace App\Http\Resources\Communications;

use App\Models\CommunicationTaskNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CommunicationTaskNote */
class CommunicationTaskNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'body' => $this->body,
            'author' => $this->whenLoaded('author', fn () => $this->author === null ? null : [
                'uuid' => $this->author->uuid,
                'name' => $this->author->displayName(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
