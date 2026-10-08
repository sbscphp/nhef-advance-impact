<?php

namespace App\Jobs;

use App\Mail\InstitutionInviteMail;
use App\Models\Admin;
use App\Models\Institution;
use App\Models\Scopes\TenantScope;
use App\Services\Auth\PasswordResetService;
use App\Services\Theme\ThemeResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Sends (or resends) the institution welcome/invite notification email; see AdminInstitutionService::invite()/resendInvite(). */
class SendInstitutionInviteEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $institutionUuid) {}

    public function handle(): void
    {
        $institution = Institution::query()->where('uuid', $this->institutionUuid)->first();

        if (! $institution instanceof Institution || $institution->email === null) {
            return;
        }

        try {
            $theme = app(ThemeResolver::class)->resolveForMail();

            $owner = Admin::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where('institution_id', $institution->id)
                ->where('must_reset_password', true)
                ->oldest('id')
                ->first();

            $passwords = app(PasswordResetService::class);
            $setPasswordUrl = $owner instanceof Admin
                ? $passwords->adminSetPasswordUrl($passwords->issueResetTokenFor($owner), null, $owner->email)
                : null;

            Mail::to($institution->email)->send(new InstitutionInviteMail(
                $institution->name,
                $theme,
                $institution->invite_message,
                $setPasswordUrl,
            ));
        } catch (\Throwable $th) {
            Log::warning('Institution invite email failed.', [
                'institution_uuid' => $institution->uuid,
                'exception' => $th::class,
                'message' => $th->getMessage(),
            ]);
        }
    }
}
