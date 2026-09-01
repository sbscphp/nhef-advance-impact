<?php

namespace Tests\Feature\Communications;

use App\Enums\TaskRecurrenceStatusEnum;
use App\Enums\TaskStatusEnum;
use App\Jobs\GenerateRecurringTaskInstanceJob;
use App\Jobs\SendTaskReminderJob;
use App\Models\Admin;
use App\Models\User;
use App\Services\Communications\CallLogService;
use App\Services\Communications\TaskService;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCallLogAndTaskLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_logging_a_call_can_create_a_follow_up_task_in_the_same_action(): void
    {
        $admin = $this->makeAdmin();
        $contact = $this->makeConstituent();
        $service = app(CallLogService::class);

        $call = $service->create([
            'contact_user_uuid' => $contact->uuid,
            'purpose' => 'Discuss gift renewal',
            'description' => 'Called about renewing their annual gift.',
            'follow_up_tasks' => [
                [
                    'title' => 'Send renewal packet',
                    'assigned_to' => $admin->uuid,
                    'priority' => 'high',
                    'start_date' => now()->toDateString(),
                    'due_date' => now()->addDays(3)->toDateString(),
                ],
                [
                    'title' => 'Confirm receipt',
                    'assigned_to' => $admin->uuid,
                    'priority' => 'low',
                    'start_date' => now()->toDateString(),
                    'due_date' => now()->addDays(5)->toDateString(),
                ],
            ],
        ], $admin, Request::create('/'));

        $this->assertSame(2, $call->followUpTasks->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'COMMUNICATION_CALL_LOGGED']);
    }

    public function test_a_follow_up_task_can_be_added_to_an_already_logged_call(): void
    {
        $admin = $this->makeAdmin();
        $contact = $this->makeConstituent();
        $service = app(CallLogService::class);

        $call = $service->create([
            'contact_user_uuid' => $contact->uuid,
            'purpose' => 'Discuss gift renewal',
            'description' => 'Called about renewing their annual gift.',
        ], $admin, Request::create('/'));

        $this->assertSame(0, $call->followUpTasks->count());

        $updated = $service->addTask($call->uuid, [
            'title' => 'Send renewal packet',
            'assigned_to' => $admin->uuid,
            'priority' => 'high',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
        ], $admin, Request::create('/'));

        $this->assertSame(1, $updated->followUpTasks->count());
    }

    public function test_call_log_overview_rolls_up_its_follow_up_tasks_not_the_calls_themselves(): void
    {
        $admin = $this->makeAdmin();
        $contact = $this->makeConstituent();
        $service = app(CallLogService::class);

        $service->create([
            'contact_user_uuid' => $contact->uuid,
            'purpose' => 'Overdue follow-up',
            'description' => 'x',
            'follow_up_tasks' => [
                [
                    'title' => 'Overdue task',
                    'assigned_to' => $admin->uuid,
                    'priority' => 'medium',
                    'start_date' => now()->subDays(5)->toDateString(),
                    'due_date' => now()->subDay()->toDateString(),
                ],
            ],
        ], $admin, Request::create('/'));

        $overview = $service->overview();
        $this->assertSame(1, $overview['total']);
        $this->assertSame(1, $overview['overdue']);
    }

    public function test_recurring_task_generates_its_next_instance_at_the_same_cadence(): void
    {
        $admin = $this->makeAdmin();
        $taskService = app(TaskService::class);

        $root = $taskService->create([
            'title' => 'Weekly check-in',
            'assigned_to' => $admin->uuid,
            'priority' => 'medium',
            'start_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
            'is_recurring' => true,
            'recurrence_end_date' => now()->addDays(90)->toDateString(),
        ], $admin, Request::create('/'));

        $this->assertSame(TaskRecurrenceStatusEnum::ACTIVE->value, $root->recurrence_status);

        app(GenerateRecurringTaskInstanceJob::class, ['rootTaskUuid' => $root->uuid])->handle();

        $instances = $taskService->listInstances($root->uuid);
        $this->assertCount(2, $instances);

        $child = $instances->firstWhere('uuid', '!=', $root->uuid);
        $this->assertNotNull($child);
        $this->assertFalse((bool) $child->is_recurring);
        $this->assertSame($root->due_date->addDays(5)->toDateString(), $child->due_date->toDateString());
    }

    public function test_recurrence_can_be_paused_and_resumed_only_via_the_root(): void
    {
        $admin = $this->makeAdmin();
        $taskService = app(TaskService::class);

        $root = $taskService->create([
            'title' => 'Monthly report',
            'assigned_to' => $admin->uuid,
            'priority' => 'low',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'is_recurring' => true,
            'recurrence_end_date' => now()->addYear()->toDateString(),
        ], $admin, Request::create('/'));

        app(GenerateRecurringTaskInstanceJob::class, ['rootTaskUuid' => $root->uuid])->handle();
        $child = $taskService->listInstances($root->uuid)->firstWhere('uuid', '!=', $root->uuid);

        // Pausing via the child instance must redirect to the root, since only the root carries
        // recurrence state.
        $paused = $taskService->pauseRecurrence($child->uuid, $admin, Request::create('/'));
        $this->assertSame($root->uuid, $paused->uuid);
        $this->assertSame(TaskRecurrenceStatusEnum::PAUSED->value, $paused->recurrence_status);

        $resumed = $taskService->resumeRecurrence($root->uuid, $admin, Request::create('/'));
        $this->assertSame(TaskRecurrenceStatusEnum::ACTIVE->value, $resumed->recurrence_status);
    }

    public function test_reminder_job_stamps_the_matching_sent_at_column_once(): void
    {
        $admin = $this->makeAdmin();
        $taskService = app(TaskService::class);

        $task = $taskService->create([
            'title' => 'Follow up with donor',
            'assigned_to' => $admin->uuid,
            'priority' => 'critical',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'reminder_1_day_before' => true,
        ], $admin, Request::create('/'));

        $this->assertNull($task->reminder_1_day_sent_at);

        app(SendTaskReminderJob::class, ['taskUuid' => $task->uuid, 'reminderType' => 'one_day_before'])->handle(
            app(NotificationDispatchService::class)
        );

        $reminded = $taskService->find($task->uuid);
        $this->assertNotNull($reminded->reminder_1_day_sent_at);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $admin->id, 'notifiable_type' => Admin::class]);
    }

    public function test_marking_a_task_done_and_adding_a_note(): void
    {
        $admin = $this->makeAdmin();
        $taskService = app(TaskService::class);

        $task = $taskService->create([
            'title' => 'One-off task',
            'assigned_to' => $admin->uuid,
            'priority' => 'low',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
        ], $admin, Request::create('/'));

        $withNote = $taskService->addNote($task->uuid, 'Left a voicemail.', $admin, Request::create('/'));
        $this->assertSame(1, $withNote->notes->count());

        $done = $taskService->markDone($task->uuid, $admin, Request::create('/'));
        $this->assertSame(TaskStatusEnum::DONE->value, $done->status);
    }

    private function makeConstituent(): User
    {
        return User::create([
            'firstname' => 'Contact',
            'lastname' => 'Person',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
        ]);
    }

    private function makeAdmin(): Admin
    {
        return Admin::create([
            'name' => 'Test Admin',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_active' => true,
            'can_login' => true,
        ]);
    }
}
