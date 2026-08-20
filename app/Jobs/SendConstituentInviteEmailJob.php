<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\Auth\ConstituentInviteSetPasswordMail;
use App\Services\Auth\PasswordResetService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/** Sends (or resends) the individual constituent's set-password invite; see AdminConstituentService::invite()/resendInvite(). */
class SendConstituentInviteEmailJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;

    public function __construct(public readonly string $userUuid) {}

    public function uniqueId(): string
    {
        return 'constituent-invite:'.$this->userUuid;
    }

    public function handle(PasswordResetService $passwordResetService): void
    {
        $user = User::query()->where('uuid', $this->userUuid)->first();

        if ($user === null) {
            return;
        }

        try {
            $token = $passwordResetService->issueResetTokenFor($user);
            $resetUrl = $passwordResetService->customerVerifyEmailUrl($token, null, $user->email);

            $user->notify(new ConstituentInviteSetPasswordMail($token, $resetUrl));
        } catch (\Throwable $th) {
            Log::warning('Constituent invite email failed.', [
                'user_uuid' => $user->uuid,
                'exception' => $th::class,
                'message' => $th->getMessage(),
            ]);
        }
    }
}
