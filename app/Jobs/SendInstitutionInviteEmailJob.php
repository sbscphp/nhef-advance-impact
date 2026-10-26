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

            // The Advancement Database Manager is this owner account (2026-10-23, client
            // requirement) - the invite goes to them, not the institution's own general email,
            // since they're the one who actually needs the password-set link below.
            if (! $owner instanceof Admin) {
                return;
            }

            $passwords = app(PasswordResetService::class);
            $setPasswordUrl = $passwords->adminSetPasswordUrl($passwords->issueResetTokenFor($owner), null, $owner->email);

            Mail::to($owner->email)->send(new InstitutionInviteMail(
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
