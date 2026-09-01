<?php

namespace App\Console\Commands;

use App\Enums\TaskRecurrenceStatusEnum;
use App\Jobs\GenerateRecurringTaskInstanceJob;
use App\Models\CommunicationTask;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateRecurringTasksCommand extends Command
{
    protected $signature = 'communications:generate-recurring-tasks';

    protected $description = 'Create the next instance of every recurring task whose current cycle is due.';

    public function handle(): int
    {
        $today = Carbon::today();

        $roots = CommunicationTask::query()
            ->whereNull('parent_task_id')
            ->where('is_recurring', true)
            ->where('recurrence_status', TaskRecurrenceStatusEnum::ACTIVE->value)
            ->get();

        $queued = 0;

        foreach ($roots as $root) {
            $latest = CommunicationTask::query()
                ->where(fn ($query) => $query->where('id', $root->id)->orWhere('parent_task_id', $root->id))
                ->orderByDesc('due_date')
                ->first();

            // The next cycle isn't generated until the current one's own due_date arrives; the
            // interval only spaces cycles apart once generation starts, it never counts from
            // start_date or "now".
            if ($latest === null || $latest->due_date->gt($today)) {
                continue;
            }

            // recurrence_end_date is always null when repeat_non_stop is true (TaskService
            // enforces this), so a "repeat forever" root never hits this cutoff.
            if ($root->recurrence_end_date !== null && $latest->due_date->gte($root->recurrence_end_date)) {
                continue;
            }

            GenerateRecurringTaskInstanceJob::dispatch($root->uuid);
            $queued++;
        }

        $this->info($queued.' recurring task instance(s) queued for generation.');

        return self::SUCCESS;
    }
}
