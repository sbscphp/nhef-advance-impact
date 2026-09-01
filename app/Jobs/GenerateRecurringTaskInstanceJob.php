<?php

namespace App\Jobs;

use App\Enums\TaskRecurrenceIntervalUnitEnum;
use App\Enums\TaskStatusEnum;
use App\Models\CommunicationTask;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/** Creates the next cycle of a recurring task, spaced by the root's own recurrence interval. The new instance is never itself recurring. */
class GenerateRecurringTaskInstanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $rootTaskUuid) {}

    public function handle(): void
    {
        $root = CommunicationTask::query()->where('uuid', $this->rootTaskUuid)->first();

        if (! $root instanceof CommunicationTask) {
            return;
        }

        try {
            $latest = CommunicationTask::query()
                ->where(fn ($query) => $query->where('id', $root->id)->orWhere('parent_task_id', $root->id))
                ->orderByDesc('due_date')
                ->first();

            if (! $latest instanceof CommunicationTask) {
                return;
            }

            // The new cycle's start is the previous cycle's due_date, and the interval is
            // applied from there, not from the root's start_date: cycles chain end-to-end.
            $cycleLength = $latest->start_date->diffInDays($latest->due_date);
            $nextStart = $latest->due_date->copy();
            $nextDue = $this->applyInterval($nextStart->copy(), $root->recurrence_interval_value, $root->recurrence_interval_unit);

            if ($cycleLength > 0 && $nextStart->diffInDays($nextDue) < $cycleLength) {
                // Guards against a very short interval (e.g. "every 1 hour") producing a due
                // date before the task's own start-to-due cycle length would allow.
                $nextDue = $nextStart->copy()->addDays($cycleLength);
            }

            CommunicationTask::create([
                'call_log_id' => $root->call_log_id,
                'parent_task_id' => $root->id,
                'title' => $root->title,
                'assigned_to' => $root->assigned_to,
                'priority' => $root->priority,
                'description' => $root->description,
                'start_date' => $nextStart,
                'due_date' => $nextDue,
                'status' => TaskStatusEnum::PENDING->value,
                'reminders_enabled' => $root->reminders_enabled,
                'reminder_2_days_before' => $root->reminder_2_days_before,
                'reminder_1_day_before' => $root->reminder_1_day_before,
                'reminder_on_due_date' => $root->reminder_on_due_date,
                'is_recurring' => false,
                'created_by' => $root->created_by,
            ]);
        } catch (\Throwable $th) {
            Log::warning('Recurring task instance generation failed.', [
                'root_task_uuid' => $root->uuid,
                'exception' => $th::class,
                'message' => $th->getMessage(),
            ]);
        }
    }

    private function applyInterval(CarbonInterface $date, ?int $value, ?string $unit): CarbonInterface
    {
        $value = max(1, $value ?? 1);

        return match ($unit) {
            TaskRecurrenceIntervalUnitEnum::HOUR->value => $date->addHours($value),
            TaskRecurrenceIntervalUnitEnum::WEEK->value => $date->addWeeks($value),
            TaskRecurrenceIntervalUnitEnum::MONTH->value => $date->addMonths($value),
            TaskRecurrenceIntervalUnitEnum::QUARTER->value => $date->addQuarters($value),
            TaskRecurrenceIntervalUnitEnum::YEAR->value => $date->addYears($value),
            default => $date->addDays($value),
        };
    }
}
