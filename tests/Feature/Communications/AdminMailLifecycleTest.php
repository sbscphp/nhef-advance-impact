<?php

namespace Tests\Feature\Communications;

use App\Enums\MailStatusEnum;
use App\Exceptions\ApiException;
use App\Jobs\SendMailCampaignJob;
use App\Mail\BulkCampaignMail;
use App\Models\Admin;
use App\Models\User;
use App\Services\Communications\MailService;
use App\Services\Theme\ThemeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminMailLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_mail_is_always_a_draft_until_sent(): void
    {
        $admin = $this->makeAdmin();
        $service = app(MailService::class);

        $mail = $service->create([
            'title' => 'Alumni Newsletter',
            'body' => '<p>Hello</p>',
        ], $admin, Request::create('/'));

        $this->assertSame(MailStatusEnum::DRAFT->value, $mail->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'MAIL_CREATED']);
    }

    public function test_send_resolves_the_segment_and_dedupes_against_individually_picked_recipients(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $matching = $this->makeConstituent('match1@example.com', 'Test University');
        $alsoPicked = $this->makeConstituent('picked@example.com', 'Other University');
        $this->makeConstituent('nomatch@example.com', 'Other University');

        $service = app(MailService::class);
        $mail = $service->create([
            'title' => 'Segment Send',
            'body' => '<p>Hi</p>',
            'segment' => ['university' => 'Test University'],
            'recipient_user_ids' => [$alsoPicked->uuid],
        ], $admin, Request::create('/'));

        $sent = $service->send($mail->uuid, [], $admin, Request::create('/'));

        $this->assertNotSame(MailStatusEnum::SENT->value, $sent->status, 'Status must not be set optimistically before the job runs.');
        $this->assertSame(
            ['match1@example.com', 'picked@example.com'],
            $sent->recipients()->orderBy('email')->pluck('email')->all()
        );
    }

    public function test_send_with_no_matching_audience_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $service = app(MailService::class);
        $mail = $service->create([
            'title' => 'Empty Segment',
            'body' => '<p>Hi</p>',
            'segment' => ['university' => 'Nonexistent University'],
        ], $admin, Request::create('/'));

        $this->expectException(ApiException::class);
        $service->send($mail->uuid, [], $admin, Request::create('/'));
    }

    public function test_job_sends_one_email_per_recipient_and_rolls_up_status(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $this->makeConstituent('a@example.com', 'Test University');
        $this->makeConstituent('b@example.com', 'Test University');

        $service = app(MailService::class);
        $mail = $service->create([
            'title' => 'Two Recipients',
            'body' => '<p>Hi</p>',
            'segment' => ['university' => 'Test University'],
        ], $admin, Request::create('/'));
        $mail = $service->send($mail->uuid, [], $admin, Request::create('/'));

        app(SendMailCampaignJob::class, ['mailUuid' => $mail->uuid])->handle(app(ThemeResolver::class));

        $processed = $mail->fresh();
        $this->assertSame(MailStatusEnum::SENT->value, $processed->status);
        $this->assertNotNull($processed->sent_at);
        Mail::assertSentTimes(BulkCampaignMail::class, 2);
    }

    public function test_open_tracking_sets_opened_at_once_and_increments_open_count(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $this->makeConstituent('opens@example.com', 'Test University');

        $service = app(MailService::class);
        $mail = $service->create([
            'title' => 'Track Me',
            'body' => '<p>Hi</p>',
            'segment' => ['university' => 'Test University'],
        ], $admin, Request::create('/'));
        $mail = $service->send($mail->uuid, [], $admin, Request::create('/'));
        app(SendMailCampaignJob::class, ['mailUuid' => $mail->uuid])->handle(app(ThemeResolver::class));

        $recipient = $mail->fresh()->recipients()->sole();

        $service->trackOpen($recipient->uuid);
        $firstOpenedAt = $recipient->fresh()->opened_at;
        $this->assertNotNull($firstOpenedAt);
        $this->assertSame(1, $recipient->fresh()->open_count);

        $service->trackOpen($recipient->uuid);
        $this->assertEquals($firstOpenedAt, $recipient->fresh()->opened_at, 'opened_at must only be set on the first hit.');
        $this->assertSame(2, $recipient->fresh()->open_count);
    }

    public function test_unsubscribe_excludes_the_recipient_from_future_sends(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $this->makeConstituent('unsub@example.com', 'Test University');

        $service = app(MailService::class);
        $first = $service->create([
            'title' => 'First Send',
            'body' => '<p>Hi</p>',
            'segment' => ['university' => 'Test University'],
        ], $admin, Request::create('/'));
        $first = $service->send($first->uuid, [], $admin, Request::create('/'));
        $recipient = $first->recipients()->sole();

        $service->unsubscribe($recipient->uuid);
        $this->assertDatabaseHas('email_unsubscribes', ['email' => 'unsub@example.com']);

        $second = $service->create([
            'title' => 'Second Send',
            'body' => '<p>Hi</p>',
            'segment' => ['university' => 'Test University'],
        ], $admin, Request::create('/'));

        $this->expectException(ApiException::class);
        $service->send($second->uuid, [], $admin, Request::create('/'));
    }

    /**
     * The scenario that motivated per-recipient tracking: 2 recipients, 1 succeeds and 1 fails.
     * A resend must retry only the failed one, never re-emailing the one who already got it.
     */
    public function test_resend_only_retries_recipients_still_owed_a_delivery(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $this->makeConstituent('ok@example.com', 'Test University');
        $this->makeConstituent('failed@example.com', 'Test University');

        $service = app(MailService::class);
        $mail = $service->create([
            'title' => 'Partial Failure',
            'body' => '<p>Hi</p>',
            'segment' => ['university' => 'Test University'],
        ], $admin, Request::create('/'));
        $mail = $service->send($mail->uuid, [], $admin, Request::create('/'));

        $recipients = $mail->recipients()->get()->keyBy('email');
        $recipients['ok@example.com']->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
        $recipients['failed@example.com']->forceFill(['status' => 'failed', 'last_error' => 'Simulated rejection'])->save();
        $mail->forceFill(['status' => MailStatusEnum::FAILED->value])->save();

        $service->resend($mail->uuid, $admin, Request::create('/'));
        app(SendMailCampaignJob::class, ['mailUuid' => $mail->uuid])->handle(app(ThemeResolver::class));

        Mail::assertSentTimes(BulkCampaignMail::class, 1);

        $final = $mail->recipients()->get()->keyBy('email');
        $this->assertSame('sent', $final['ok@example.com']->status);
        $this->assertSame('sent', $final['failed@example.com']->status);
        $this->assertSame(MailStatusEnum::SENT->value, $mail->fresh()->status);
    }

    public function test_only_a_draft_can_be_updated_or_deleted(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $this->makeConstituent('locked@example.com', 'Test University');

        $service = app(MailService::class);
        $mail = $service->create([
            'title' => 'Locked Once Sent',
            'body' => '<p>Hi</p>',
            'segment' => ['university' => 'Test University'],
        ], $admin, Request::create('/'));
        $mail = $service->send($mail->uuid, [], $admin, Request::create('/'));

        $this->expectException(ApiException::class);
        $service->update($mail->uuid, ['title' => 'New Title'], $admin, Request::create('/'));
    }

    private function makeConstituent(string $email, string $university): User
    {
        return User::create([
            'firstname' => 'Test',
            'lastname' => 'Constituent',
            'email' => $email,
            'password' => Hash::make('password'),
            'university' => $university,
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
