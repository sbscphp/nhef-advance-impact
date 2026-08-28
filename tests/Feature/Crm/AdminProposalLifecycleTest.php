<?php

namespace Tests\Feature\Crm;

use App\Enums\ProposalCollaboratorRoleEnum;
use App\Enums\ProposalStatusEnum;
use App\Exceptions\ApiException;
use App\Jobs\SendProposalToClientJob;
use App\Mail\ProposalSentMail;
use App\Models\Admin;
use App\Models\Prospect;
use App\Services\Crm\ProposalService;
use App\Services\Crm\ProspectService;
use App\Services\Theme\ThemeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminProposalLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_proposal_auto_adds_the_creator_as_owner(): void
    {
        $admin = $this->makeAdmin();
        $prospect = $this->makeProspect($admin);
        $service = app(ProposalService::class);

        $proposal = $service->create($prospect->uuid, ['title' => 'Alumni Scholarship Fund'], $admin, Request::create('/'));

        $this->assertSame(ProposalStatusEnum::DRAFT->value, $proposal->status);
        $this->assertSame(ProposalCollaboratorRoleEnum::OWNER, $proposal->collaboratorRole($admin));
        $this->assertDatabaseHas('audit_logs', ['action' => 'PROSPECT_PROPOSAL_CREATED']);
    }

    public function test_a_draft_cannot_be_sent_or_resent_to_a_client(): void
    {
        $admin = $this->makeAdmin();
        $prospect = $this->makeProspect($admin);
        $service = app(ProposalService::class);
        $draft = $service->create($prospect->uuid, ['title' => 'Still a draft'], $admin, Request::create('/'));

        $payload = ['message_title' => 'Hi', 'message_body' => 'Body', 'recipient_emails' => ['donor@example.com']];

        try {
            $service->sendToClient($prospect->uuid, $draft->uuid, $payload, $admin, Request::create('/'));
            $this->fail('A draft proposal should not be sendable.');
        } catch (ApiException $e) {
            $this->assertSame(422, $e->status);
        }

        $saved = $service->update($prospect->uuid, $draft->uuid, ['body' => '<p>ready</p>', 'save_as' => 'save'], $admin, Request::create('/'));
        $sent = $service->sendToClient($prospect->uuid, $saved->uuid, $payload, $admin, Request::create('/'));
        $this->assertSame(ProposalStatusEnum::PENDING->value, $sent->status);
    }

    public function test_save_as_draft_versus_save_transitions(): void
    {
        $admin = $this->makeAdmin();
        $prospect = $this->makeProspect($admin);
        $service = app(ProposalService::class);
        $proposal = $service->create($prospect->uuid, ['title' => 'Draft Me'], $admin, Request::create('/'));

        $stillDraft = $service->update($prospect->uuid, $proposal->uuid, ['body' => '<p>work in progress</p>', 'save_as' => 'draft'], $admin, Request::create('/'));
        $this->assertSame(ProposalStatusEnum::DRAFT->value, $stillDraft->status);

        $saved = $service->update($prospect->uuid, $proposal->uuid, ['body' => '<p>ready</p>', 'save_as' => 'save'], $admin, Request::create('/'));
        $this->assertSame(ProposalStatusEnum::PENDING->value, $saved->status);

        // Editing an already-saved proposal again must not bounce it back to draft.
        $editedAgain = $service->update($prospect->uuid, $proposal->uuid, ['body' => '<p>tweak</p>'], $admin, Request::create('/'));
        $this->assertSame(ProposalStatusEnum::PENDING->value, $editedAgain->status);
    }

    public function test_a_viewer_cannot_edit_but_an_editor_can(): void
    {
        $owner = $this->makeAdmin();
        $viewer = $this->makeAdmin();
        $editor = $this->makeAdmin();
        $prospect = $this->makeProspect($owner);
        $service = app(ProposalService::class);
        $proposal = $service->create($prospect->uuid, ['title' => 'Shared Doc'], $owner, Request::create('/'));

        $service->inviteCollaborators($prospect->uuid, $proposal->uuid, [
            ['admin_id' => $viewer->uuid, 'role' => 'viewer'],
            ['admin_id' => $editor->uuid, 'role' => 'editor'],
        ], $owner, Request::create('/'));

        $this->expectException(ApiException::class);
        $service->update($prospect->uuid, $proposal->uuid, ['body' => '<p>nope</p>'], $viewer, Request::create('/'));
    }

    public function test_an_invited_editor_can_edit(): void
    {
        $owner = $this->makeAdmin();
        $editor = $this->makeAdmin();
        $prospect = $this->makeProspect($owner);
        $service = app(ProposalService::class);
        $proposal = $service->create($prospect->uuid, ['title' => 'Shared Doc'], $owner, Request::create('/'));

        $service->inviteCollaborators($prospect->uuid, $proposal->uuid, [
            ['admin_id' => $editor->uuid, 'role' => 'editor'],
        ], $owner, Request::create('/'));

        $updated = $service->update($prospect->uuid, $proposal->uuid, ['body' => '<p>edited by collaborator</p>'], $editor, Request::create('/'));
        $this->assertSame('<p>edited by collaborator</p>', $updated->body);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PROSPECT_PROPOSAL_COLLABORATOR_INVITED']);
    }

    public function test_duplicate_creates_a_new_owned_draft_with_a_copy_title(): void
    {
        $admin = $this->makeAdmin();
        $prospect = $this->makeProspect($admin);
        $service = app(ProposalService::class);
        $original = $service->create($prospect->uuid, ['title' => 'New Proposal, 2026', 'body' => '<p>content</p>'], $admin, Request::create('/'));

        $duplicate = $service->duplicate($prospect->uuid, $original->uuid, $admin, Request::create('/'));

        $this->assertSame('New Proposal, 2026 - Copy (01)', $duplicate->title);
        $this->assertSame('<p>content</p>', $duplicate->body);
        $this->assertSame(ProposalStatusEnum::DRAFT->value, $duplicate->status);
        $this->assertSame(ProposalCollaboratorRoleEnum::OWNER, $duplicate->collaboratorRole($admin));
    }

    public function test_delete_is_owner_only(): void
    {
        $owner = $this->makeAdmin();
        $editor = $this->makeAdmin();
        $prospect = $this->makeProspect($owner);
        $service = app(ProposalService::class);
        $proposal = $service->create($prospect->uuid, ['title' => 'Delete Me'], $owner, Request::create('/'));

        $service->inviteCollaborators($prospect->uuid, $proposal->uuid, [
            ['admin_id' => $editor->uuid, 'role' => 'editor'],
        ], $owner, Request::create('/'));

        try {
            $service->delete($prospect->uuid, $proposal->uuid, $editor, Request::create('/'));
            $this->fail('An editor should not be able to delete a proposal.');
        } catch (ApiException $e) {
            $this->assertSame(403, $e->status);
        }

        $service->delete($prospect->uuid, $proposal->uuid, $owner, Request::create('/'));
        $this->assertDatabaseMissing('prospect_proposals', ['uuid' => $proposal->uuid]);
    }

    public function test_send_to_client_queues_and_only_the_job_marks_it_sent(): void
    {
        Mail::fake();
        Bus::fake();

        $admin = $this->makeAdmin();
        $prospect = $this->makeProspect($admin);
        $service = app(ProposalService::class);
        $proposal = $service->create($prospect->uuid, ['title' => 'Send Me', 'body' => '<p>Proposal content</p>'], $admin, Request::create('/'));
        $proposal = $service->update($prospect->uuid, $proposal->uuid, ['save_as' => 'save'], $admin, Request::create('/'));

        $sent = $service->sendToClient($prospect->uuid, $proposal->uuid, [
            'message_title' => 'New Proposal to Client',
            'message_body' => 'Kindly find attached our proposal.',
            'recipient_emails' => ['donor@example.com', 'partner@example.org'],
        ], $admin, Request::create('/'));

        // The prospect's own registered email is always included ahead of whatever the admin typed.
        $this->assertSame(
            ['chinedu.okafor@example.com', 'donor@example.com', 'partner@example.org'],
            $sent->recipients()->orderBy('email')->pluck('email')->all()
        );
        $this->assertNotSame(ProposalStatusEnum::SENT->value, $sent->status);
        $this->assertNull($sent->sent_at);
        Bus::assertDispatched(SendProposalToClientJob::class);
    }

    public function test_send_to_client_works_with_no_additional_recipients_and_dedupes_the_prospect(): void
    {
        Bus::fake();

        $admin = $this->makeAdmin();
        $prospect = $this->makeProspect($admin);
        $service = app(ProposalService::class);
        $proposal = $service->create($prospect->uuid, ['title' => 'Prospect Only', 'body' => '<p>x</p>'], $admin, Request::create('/'));
        $proposal = $service->update($prospect->uuid, $proposal->uuid, ['save_as' => 'save'], $admin, Request::create('/'));

        $sent = $service->sendToClient($prospect->uuid, $proposal->uuid, [
            'message_title' => 'Hi', 'message_body' => 'Body',
        ], $admin, Request::create('/'));
        $this->assertSame(['chinedu.okafor@example.com'], $sent->recipients()->pluck('email')->all());

        // Admin re-typing the prospect's own address explicitly must not duplicate it.
        $proposal2 = $service->create($prospect->uuid, ['title' => 'Prospect Typed Too'], $admin, Request::create('/'));
        $proposal2 = $service->update($prospect->uuid, $proposal2->uuid, ['save_as' => 'save'], $admin, Request::create('/'));
        $sent2 = $service->sendToClient($prospect->uuid, $proposal2->uuid, [
            'message_title' => 'Hi', 'message_body' => 'Body',
            'recipient_emails' => ['chinedu.okafor@example.com', 'partner@example.org'],
        ], $admin, Request::create('/'));
        $this->assertSame(
            ['chinedu.okafor@example.com', 'partner@example.org'],
            $sent2->recipients()->orderBy('email')->pluck('email')->all()
        );
    }

    public function test_send_proposal_to_client_job_sends_mail_and_marks_sent(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $prospect = $this->makeProspect($admin);
        $service = app(ProposalService::class);
        $proposal = $service->create($prospect->uuid, ['title' => 'Send Me', 'body' => '<p>Proposal content</p>'], $admin, Request::create('/'));
        $proposal = $service->update($prospect->uuid, $proposal->uuid, ['save_as' => 'save'], $admin, Request::create('/'));

        $sent = $service->sendToClient($prospect->uuid, $proposal->uuid, [
            'message_title' => 'New Proposal to Client',
            'message_body' => 'Kindly find attached our proposal.',
            'recipient_emails' => ['donor@example.com', 'partner@example.org'],
        ], $admin, Request::create('/'));

        app(SendProposalToClientJob::class, ['proposalUuid' => $sent->uuid])
            ->handle(app(ProposalService::class), app(ThemeResolver::class));

        $processed = $sent->fresh();
        $this->assertSame(ProposalStatusEnum::SENT->value, $processed->status);
        $this->assertNotNull($processed->sent_at);
        Mail::assertSent(ProposalSentMail::class);
    }

    public function test_pdf_and_word_downloads_render(): void
    {
        $admin = $this->makeAdmin();
        $prospect = $this->makeProspect($admin);
        $service = app(ProposalService::class);
        $proposal = $service->create($prospect->uuid, [
            'title' => 'Downloadable Proposal',
            'body' => '<h2>Executive Summary</h2><p>This is the plan.</p>',
        ], $admin, Request::create('/'));

        $pdfResponse = $service->downloadPdf($prospect->uuid, $proposal->uuid);
        $this->assertSame('application/pdf', $pdfResponse->headers->get('Content-Type'));
        $this->assertNotEmpty($pdfResponse->getContent());

        $wordResponse = $service->downloadWord($prospect->uuid, $proposal->uuid);
        $this->assertNotEmpty($wordResponse->getContent());
    }

    public function test_a_failed_send_is_tracked_and_recoverable_via_resend(): void
    {
        $admin = $this->makeAdmin();
        $prospect = $this->makeProspect($admin);
        $service = app(ProposalService::class);
        $proposal = $service->create($prospect->uuid, ['title' => 'Flaky Send', 'body' => '<p>content</p>'], $admin, Request::create('/'));
        $proposal = $service->update($prospect->uuid, $proposal->uuid, ['save_as' => 'save'], $admin, Request::create('/'));

        $proposal = $service->sendToClient($prospect->uuid, $proposal->uuid, [
            'message_title' => 'Hi',
            'message_body' => 'Body',
            'recipient_emails' => ['donor@example.com'],
        ], $admin, Request::create('/'));

        // Force the mail transport to fail without touching real config, to exercise the job's
        // catch branch instead of mocking it away.
        config(['mail.mailers.smtp.host' => 'invalid.invalid.invalid', 'mail.mailers.smtp.transport' => 'smtp']);
        config(['mail.default' => 'smtp']);

        app(SendProposalToClientJob::class, ['proposalUuid' => $proposal->uuid])->handle(app(ProposalService::class), app(ThemeResolver::class));

        $failed = $proposal->fresh();
        $this->assertSame(ProposalStatusEnum::FAILED->value, $failed->status);
        $recipient = $failed->recipients()->sole();
        $this->assertSame('failed', $recipient->status);
        $this->assertSame(1, $recipient->attempts);
        $this->assertNotNull($recipient->last_attempted_at);
        $this->assertNotNull($recipient->last_error);

        config(['mail.default' => 'array']);
        Mail::fake();

        $service->resend($prospect->uuid, $proposal->uuid, $admin, Request::create('/'));
        app(SendProposalToClientJob::class, ['proposalUuid' => $proposal->uuid])->handle(app(ProposalService::class), app(ThemeResolver::class));

        $recovered = $proposal->fresh();
        $this->assertSame(ProposalStatusEnum::SENT->value, $recovered->status);
        $recoveredRecipient = $recovered->recipients()->sole();
        $this->assertSame('sent', $recoveredRecipient->status);
        $this->assertSame(2, $recoveredRecipient->attempts);
        $this->assertNull($recoveredRecipient->last_error);
        Mail::assertSent(ProposalSentMail::class);
    }

    /**
     * The scenario that motivated per-recipient tracking: 3 recipients, 2 succeed and 1 fails.
     * A resend must retry only the failed one, never re-emailing the 2 who already got it.
     */
    public function test_resend_only_retries_recipients_still_owed_a_delivery(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $prospect = $this->makeProspect($admin);
        $service = app(ProposalService::class);
        $proposal = $service->create($prospect->uuid, ['title' => 'Triple Send', 'body' => '<p>x</p>'], $admin, Request::create('/'));
        $proposal = $service->update($prospect->uuid, $proposal->uuid, ['save_as' => 'save'], $admin, Request::create('/'));
        $proposal = $service->sendToClient($prospect->uuid, $proposal->uuid, [
            'message_title' => 'Hi', 'message_body' => 'Body',
            'recipient_emails' => ['donor@example.com', 'partner@example.org'],
        ], $admin, Request::create('/'));

        // Simulate a job run that already succeeded for 2 of the 3 and failed for the third,
        // without needing to fake a real per-address SMTP rejection.
        $recipients = $proposal->recipients()->get();
        $recipients->where('email', '!=', 'partner@example.org')->each(
            fn ($r) => $r->forceFill(['status' => 'sent', 'sent_at' => now()->subHour(), 'attempts' => 1])->save()
        );
        $recipients->firstWhere('email', 'partner@example.org')
            ->forceFill(['status' => 'failed', 'attempts' => 1, 'last_error' => 'Simulated rejection'])->save();
        $proposal->forceFill(['status' => ProposalStatusEnum::FAILED->value])->save();

        $service->resend($prospect->uuid, $proposal->uuid, $admin, Request::create('/'));
        app(SendProposalToClientJob::class, ['proposalUuid' => $proposal->uuid])->handle(app(ProposalService::class), app(ThemeResolver::class));

        Mail::assertSentTimes(ProposalSentMail::class, 1);

        $final = $proposal->recipients()->get()->keyBy('email');
        $this->assertSame(1, $final['chinedu.okafor@example.com']->attempts, 'Already-sent recipients must not be re-attempted.');
        $this->assertSame(1, $final['donor@example.com']->attempts);
        $this->assertSame(2, $final['partner@example.org']->attempts, 'Only the failed recipient gets retried.');
        $this->assertSame('sent', $final['partner@example.org']->status);
        $this->assertSame(ProposalStatusEnum::SENT->value, $proposal->fresh()->status);
    }

    public function test_an_already_sent_proposal_cannot_be_sent_or_resent_again(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $prospect = $this->makeProspect($admin);
        $service = app(ProposalService::class);
        $proposal = $service->create($prospect->uuid, ['title' => 'One and Done', 'body' => '<p>content</p>'], $admin, Request::create('/'));
        $proposal = $service->update($prospect->uuid, $proposal->uuid, ['save_as' => 'save'], $admin, Request::create('/'));

        $payload = ['message_title' => 'Hi', 'message_body' => 'Body', 'recipient_emails' => ['donor@example.com']];
        $proposal = $service->sendToClient($prospect->uuid, $proposal->uuid, $payload, $admin, Request::create('/'));
        app(SendProposalToClientJob::class, ['proposalUuid' => $proposal->uuid])->handle(app(ProposalService::class), app(ThemeResolver::class));

        $this->assertSame(ProposalStatusEnum::SENT->value, $proposal->fresh()->status);

        try {
            $service->sendToClient($prospect->uuid, $proposal->uuid, $payload, $admin, Request::create('/'));
            $this->fail('An already-sent proposal should not be sendable again.');
        } catch (ApiException $e) {
            $this->assertSame(422, $e->status);
        }

        try {
            $service->resend($prospect->uuid, $proposal->uuid, $admin, Request::create('/'));
            $this->fail('An already-sent proposal should not be resendable.');
        } catch (ApiException $e) {
            $this->assertSame(422, $e->status);
        }
    }

    public function test_resend_requires_a_prior_send_attempt(): void
    {
        $admin = $this->makeAdmin();
        $prospect = $this->makeProspect($admin);
        $service = app(ProposalService::class);
        $proposal = $service->create($prospect->uuid, ['title' => 'Never Sent'], $admin, Request::create('/'));

        $this->expectException(ApiException::class);
        $service->resend($prospect->uuid, $proposal->uuid, $admin, Request::create('/'));
    }

    private function makeProspect(Admin $assignee): Prospect
    {
        return app(ProspectService::class)->create([
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
            'name' => 'Test Admin',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_active' => true,
            'can_login' => true,
        ]);
    }
}
