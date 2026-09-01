<?php

namespace App\Http\Resources\Communications;

use App\Models\CommunicationTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lean row shape for the "Recurring History" table (Date/Assign To/Due Date/Status/Action
 * columns only); the "Action" eye icon opens the full task via `GET /tasks/{uuid}` separately,
 * so nothing else here needs to carry reminders/recurrence/notes/description.
 *
 * @mixin CommunicationTask
 */
class CommunicationTaskInstanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->status,
            'view' => $this->resource->computedView(),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee === null ? null : [
                'uuid' => $this->assignee->uuid,
                'name' => $this->assignee->displayName(),
            ]),
        ];
    }
}
