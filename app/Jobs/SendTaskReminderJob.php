<?php

namespace App\Jobs;

use App\Enums\ModuleEnums;
use App\Models\CommunicationTask;
use App\Notifications\GenericDatabaseNotification;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/** Notifies a task's assignee and stamps the matching `reminder_*_sent_at` column exactly once. */
class SendTaskReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $taskUuid,
        public readonly string $reminderType,
    ) {}

    public function handle(NotificationDispatchService $notificationDispatchService): void
    {
        $task = CommunicationTask::query()->where('uuid', $this->taskUuid)->first();

        if (! $task instanceof CommunicationTask) {
            return;
        }

        $sentColumn = match ($this->reminderType) {
            'two_days_before' => 'reminder_2_days_sent_at',
            'one_day_before' => 'reminder_1_day_sent_at',
            'on_due_date' => 'reminder_on_due_sent_at',
            default => null,
        };

        if ($sentColumn === null) {
            return;
        }

        try {
            $notificationDispatchService->notifyAdminsByUuids([$task->assigned_to], new GenericDatabaseNotification(
                module: ModuleEnums::communications->value,
                event: 'task_reminder',
                title: 'Task reminder: '.$task->title,
                message: 'Your task "'.$task->title.'" is due on '.$task->due_date->toFormattedDateString().'.',
                meta: ['task_uuid' => $task->uuid],
                sendMail: true,
            ));

            $task->forceFill([$sentColumn => now()])->save();
        } catch (\Throwable $th) {
            Log::warning('Task reminder dispatch failed.', [
                'task_uuid' => $task->uuid,
                'reminder_type' => $this->reminderType,
                'exception' => $th::class,
                'message' => $th->getMessage(),
            ]);
        }
    }
}
