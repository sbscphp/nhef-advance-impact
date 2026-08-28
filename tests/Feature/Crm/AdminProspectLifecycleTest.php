<?php

namespace Tests\Feature\Crm;

use App\Enums\ProspectMessageStatusEnum;
use App\Enums\ProspectPipelineStageEnum;
use App\Exceptions\ApiException;
use App\Jobs\SendProspectMessageJob;
use App\Mail\ProspectMessageMail;
use App\Models\Admin;
use App\Services\Crm\ProspectService;
use App\Services\Theme\ThemeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminProspectLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_view_a_prospect(): void
    {
        $admin = $this->makeAdmin();
        $assignee = $this->makeAdmin();
        $service = app(ProspectService::class);

        $prospect = $service->create([
            'first_name' => 'Obinna',
            'last_name' => 'Eze',
            'email' => 'obinna.eze@example.com',
            'phone' => '+2348012345678',
            'lead_source' => 'Alumni',
            'estimated_value' => 12000,
            'assign_to' => $assignee->uuid,
        ], $admin, Request::create('/'));

        $this->assertSame(ProspectPipelineStageEnum::IDENTIFICATION->value, $prospect->stage);
        $this->assertSame('NGN', $prospect->currency);
        $this->assertSame($assignee->id, $prospect->assigned_admin_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PROSPECT_CREATED']);

        $found = $service->findForAdmin($prospect->uuid);
        $this->assertSame('Obinna Eze', $found->fullName());
    }

    public function test_admin_can_update_a_prospect(): void
    {
        $admin = $this->makeAdmin();
        $service = app(ProspectService::class);
        $prospect = $this->makeProspect($service, $admin);

        $updated = $service->update($prospect->uuid, ['phone' => '+2348099999999'], $admin, Request::create('/'));

        $this->assertSame('+2348099999999', $updated->phone);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PROSPECT_UPDATED']);
    }

    public function test_reassigning_a_prospect_reflects_the_new_assignee_in_the_same_response(): void
    {
        $admin = $this->makeAdmin();
        $newAssignee = $this->makeAdmin();
        $service = app(ProspectService::class);
        $prospect = $this->makeProspect($service, $admin);

        $updated = $service->update($prospect->uuid, ['assign_to' => $newAssignee->uuid], $admin, Request::create('/'));

        $this->assertSame($newAssignee->id, $updated->assigned_admin_id);
        // Regression: the returned model must not carry a stale `assignedAdmin` relation loaded
        // before the reassignment; see ProspectService::update()'s re-fetch after saving.
        $this->assertTrue($updated->relationLoaded('assignedAdmin'));
        $this->assertSame($newAssignee->id, $updated->assignedAdmin->id);
    }

    public function test_admin_can_change_stage_and_rejects_an_invalid_one(): void
    {
        $admin = $this->makeAdmin();
        $service = app(ProspectService::class);
        $prospect = $this->makeProspect($service, $admin);

        $moved = $service->changeStage($prospect->uuid, ProspectPipelineStageEnum::CULTIVATION->value, $admin, Request::create('/'));

        $this->assertSame(ProspectPipelineStageEnum::CULTIVATION->value, $moved->stage);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PROSPECT_STAGE_CHANGED']);

        $this->expectException(ApiException::class);
        $service->changeStage($prospect->uuid, 'not-a-real-stage', $admin, Request::create('/'));
    }

    public function test_admin_can_log_a_call(): void
    {
        $admin = $this->makeAdmin();
        $service = app(ProspectService::class);
        $prospect = $this->makeProspect($service, $admin);

        $call = $service->logCall($prospect->uuid, [
            'call_purpose' => 'Follow Up Call',
            'description' => 'Discussed pledge renewal.',
        ], $admin, Request::create('/'));

        $this->assertSame('Follow Up Call', $call->purpose);
        $this->assertSame($admin->uuid, $call->logged_by);
        $this->assertSame('medium', $call->priority);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PROSPECT_CALL_LOGGED']);

        $urgent = $service->logCall($prospect->uuid, [
            'call_purpose' => 'Escalation Call',
            'description' => 'Donor threatening to withdraw pledge.',
            'priority' => 'critical',
        ], $admin, Request::create('/'));

        $this->assertSame('critical', $urgent->priority);
    }

    public function test_invite_type_determines_which_location_field_is_stored(): void
    {
        $admin = $this->makeAdmin();
        $service = app(ProspectService::class);
        $prospect = $this->makeProspect($service, $admin);

        $online = $service->sendInvite($prospect->uuid, [
            'title' => 'Thank You Call',
            'description' => 'Join us to say thank you.',
            'start_date' => '2026-01-20',
            'end_date' => '2026-01-20',
            'time' => '10:00',
            'invite_type' => 'online',
            'virtual_link' => 'https://meet.google.com/abc-defg-hij',
            'venue' => 'This should be ignored for an online invite.',
        ], $admin, Request::create('/'));

        $this->assertSame('https://meet.google.com/abc-defg-hij', $online->virtual_link);
        $this->assertNull($online->venue);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PROSPECT_INVITE_SENT']);

        $physical = $service->sendInvite($prospect->uuid, [
            'title' => 'On-Campus Visit',
            'description' => 'Come tour the new library wing.',
            'start_date' => '2026-01-20',
            'end_date' => '2026-01-20',
            'time' => '10:00',
            'invite_type' => 'physical',
            'venue' => 'NHEF Campus, Lagos',
        ], $admin, Request::create('/'));

        $this->assertSame('NHEF Campus, Lagos', $physical->venue);
        $this->assertNull($physical->virtual_link);
    }

    public function test_compose_message_queues_an_undelayed_job_for_an_immediate_send(): void
    {
        Mail::fake();
        Bus::fake();

        $admin = $this->makeAdmin();
        $service = app(ProspectService::class);
        $prospect = $this->makeProspect($service, $admin);

        $message = $service->composeMessage($prospect->uuid, [
            'subject' => 'Follow Up Reminder',
            'send_date' => now()->subMinute()->toDateTimeString(),
            'body' => '<p>Thank you for your continued support.</p>',
        ], $admin, Request::create('/'));

        // Queued either way; status only flips once the job runs (see test below).
        $this->assertSame(ProspectMessageStatusEnum::SCHEDULED->value, $message->status);
        $this->assertNull($message->sent_at);
        Bus::assertDispatched(SendProspectMessageJob::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PROSPECT_MESSAGE_SENT']);
    }

    public function test_compose_message_queues_a_delayed_job_for_a_future_send(): void
    {
        Mail::fake();
        Bus::fake();

        $admin = $this->makeAdmin();
        $service = app(ProspectService::class);
        $prospect = $this->makeProspect($service, $admin);

        $message = $service->composeMessage($prospect->uuid, [
            'subject' => 'March Newsletter',
            'send_date' => now()->addDay()->toDateTimeString(),
            'body' => '<p>Here is what is coming up.</p>',
        ], $admin, Request::create('/'));

        $this->assertSame(ProspectMessageStatusEnum::SCHEDULED->value, $message->status);
        $this->assertNull($message->sent_at);
        Bus::assertDispatched(SendProspectMessageJob::class);
    }

    /** Exercises what a queue worker actually does, decoupled from how/when it gets dispatched. */
    public function test_send_prospect_message_job_marks_the_message_sent(): void
    {
        Mail::fake();
        Bus::fake();

        $admin = $this->makeAdmin();
        $service = app(ProspectService::class);
        $prospect = $this->makeProspect($service, $admin);

        $message = $service->composeMessage($prospect->uuid, [
            'subject' => 'Follow Up Reminder',
            'send_date' => now()->subMinute()->toDateTimeString(),
            'body' => '<p>Thank you for your continued support.</p>',
        ], $admin, Request::create('/'));

        app(SendProspectMessageJob::class, ['prospectMessageUuid' => $message->uuid])->handle(app(ThemeResolver::class));

        $sent = $message->fresh();
        $this->assertSame(ProspectMessageStatusEnum::SENT->value, $sent->status);
        $this->assertNotNull($sent->sent_at);
        Mail::assertSent(ProspectMessageMail::class);
    }

    private function makeProspect(ProspectService $service, Admin $assignee)
    {
        return $service->create([
            'first_name' => 'Chinedu',
            'last_name' => 'Okafor',
            'email' => 'chinedu.okafor@example.com',
            'lead_source' => 'Referral',
            'estimated_value' => 9500,
            'assign_to' => $assignee->uuid,
        ], $assignee, Request::create('/'));
    }

    private function makeAdmin(): Admin
    {
        return Admin::create([
            'name' => 'Super Admin',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_active' => true,
            'can_login' => true,
        ]);
    }
}
