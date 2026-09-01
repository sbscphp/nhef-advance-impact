<?php

namespace App\Http\Resources\Communications;

use App\Enums\TaskStatusEnum;
use App\Models\CommunicationTask;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CommunicationTask */
class CommunicationTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'priority' => $this->priority,
            'description' => $this->description,
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->status,
            'view' => $this->resource->computedView(),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee === null ? null : [
                'uuid' => $this->assignee->uuid,
                'name' => $this->assignee->displayName(),
            ]),
            'call_log' => $this->whenLoaded('callLog', fn () => $this->callLog === null ? null : [
                'uuid' => $this->callLog->uuid,
                'purpose' => $this->callLog->purpose,
                'contact' => $this->callLog->relationLoaded('contact') && $this->callLog->contact !== null
                    ? ['uuid' => $this->callLog->contact->uuid, 'name' => $this->callLog->contact->displayName()]
                    : null,
            ]),
            // `enabled` here is the master switch ("Enable/Disable Automated Reminders" in the
            // design); a window's own `enabled` still has to be true too for that reminder to fire.
            'reminders' => [
                'enabled' => (bool) $this->reminders_enabled,
                'two_days_before' => ['enabled' => (bool) $this->reminder_2_days_before, 'sent_at' => $this->reminder_2_days_sent_at?->toIso8601String()],
                'one_day_before' => ['enabled' => (bool) $this->reminder_1_day_before, 'sent_at' => $this->reminder_1_day_sent_at?->toIso8601String()],
                'on_due_date' => ['enabled' => (bool) $this->reminder_on_due_date, 'sent_at' => $this->reminder_on_due_sent_at?->toIso8601String()],
                // Date precision only: reminders fire once a day, not at a specific time.
                'next_reminder_at' => $this->nextReminderAt()?->toIso8601String(),
            ],
            'recurrence' => [
                'is_recurring' => (bool) $this->is_recurring,
                'status' => $this->recurrence_status,
                'repeat_non_stop' => (bool) $this->repeat_non_stop,
                'interval_value' => $this->recurrence_interval_value,
                'interval_unit' => $this->recurrence_interval_unit,
                // Null whenever repeat_non_stop is true: the task recurs indefinitely.
                'end_date' => $this->recurrence_end_date?->toDateString(),
                'is_instance' => $this->parent_task_id !== null,
                // Set only by TaskService::findForDisplay(); always null on list rows.
                'next_instance_at' => $this->next_instance_at,
            ],
            'notes' => CommunicationTaskNoteResource::collection($this->whenLoaded('notes')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function nextReminderAt(): ?CarbonInterface
    {
        if (! $this->reminders_enabled || $this->status === TaskStatusEnum::DONE->value || $this->due_date === null) {
            return null;
        }

        $candidates = [];

        if ($this->reminder_2_days_before && $this->reminder_2_days_sent_at === null) {
            $candidates[] = $this->due_date->copy()->subDays(2);
        }
        if ($this->reminder_1_day_before && $this->reminder_1_day_sent_at === null) {
            $candidates[] = $this->due_date->copy()->subDay();
        }
        if ($this->reminder_on_due_date && $this->reminder_on_due_sent_at === null) {
            $candidates[] = $this->due_date->copy();
        }

        return $candidates === [] ? null : min($candidates);
    }
}
