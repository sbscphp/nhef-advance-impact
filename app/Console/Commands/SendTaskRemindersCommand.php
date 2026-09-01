<?php

namespace App\Console\Commands;

use App\Enums\TaskStatusEnum;
use App\Jobs\SendTaskReminderJob;
use App\Models\CommunicationTask;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendTaskRemindersCommand extends Command
{
    protected $signature = 'communications:send-task-reminders';

    protected $description = 'Notify assignees of tasks whose 2-days-before/1-day-before/due-date reminder window has arrived.';

    /** @var list<array{column: string, sentColumn: string, offset: int, type: string}> */
    private const WINDOWS = [
        ['column' => 'reminder_2_days_before', 'sentColumn' => 'reminder_2_days_sent_at', 'offset' => 2, 'type' => 'two_days_before'],
        ['column' => 'reminder_1_day_before', 'sentColumn' => 'reminder_1_day_sent_at', 'offset' => 1, 'type' => 'one_day_before'],
        ['column' => 'reminder_on_due_date', 'sentColumn' => 'reminder_on_due_sent_at', 'offset' => 0, 'type' => 'on_due_date'],
    ];

    public function handle(): int
    {
        $today = Carbon::today();
        $queued = 0;

        foreach (self::WINDOWS as $window) {
            $targetDate = $today->copy()->addDays($window['offset']);

            $tasks = CommunicationTask::query()
                ->where('status', TaskStatusEnum::PENDING->value)
                ->where('reminders_enabled', true)
                ->where($window['column'], true)
                ->whereNull($window['sentColumn'])
                ->whereDate('due_date', $targetDate)
                ->get();

            foreach ($tasks as $task) {
                SendTaskReminderJob::dispatch($task->uuid, $window['type']);
                $queued++;
            }
        }

        $this->info($queued.' task reminder(s) queued.');

        return self::SUCCESS;
    }
}
