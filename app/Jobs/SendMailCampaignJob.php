<?php

namespace App\Jobs;

use App\Enums\MailRecipientStatusEnum;
use App\Enums\MailStatusEnum;
use App\Mail\BulkCampaignMail;
use App\Models\Mail as MailCampaign;
use App\Models\MailRecipient;
use App\Models\Theme;
use App\Services\Theme\ThemeResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail as MailFacade;
use Illuminate\Support\Facades\URL;

/** Sends one email per recipient, appending a per-recipient tracking pixel and unsubscribe link. */
class SendMailCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $mailUuid) {}

    public function handle(ThemeResolver $themeResolver): void
    {
        $mail = MailCampaign::query()
            ->with(['recipients'])
            ->where('uuid', $this->mailUuid)
            ->first();

        if (! $mail instanceof MailCampaign || $mail->recipients->isEmpty()) {
            return;
        }

        $outstanding = $mail->recipients->whereIn('status', [
            MailRecipientStatusEnum::PENDING->value,
            MailRecipientStatusEnum::FAILED->value,
        ]);

        if ($outstanding->isEmpty()) {
            return;
        }

        $theme = $themeResolver->resolveForMail();

        foreach ($outstanding as $recipient) {
            $this->sendToOneRecipient($recipient, $mail, $theme);
        }

        $this->updateMailRollupStatus($mail);
    }

    private function sendToOneRecipient(MailRecipient $recipient, MailCampaign $mail, Theme $theme): void
    {
        try {
            $unsubscribeUrl = URL::signedRoute('mails.unsubscribe', [$recipient->uuid]);
            $bodyHtml = $mail->body.$this->footerHtml($mail, $recipient, $unsubscribeUrl);

            MailFacade::to($recipient->email)->send(new BulkCampaignMail(
                $recipient->user?->displayName() ?? '',
                $theme,
                $mail->title,
                $bodyHtml,
                $unsubscribeUrl,
            ));

            $recipient->forceFill([
                'status' => MailRecipientStatusEnum::SENT->value,
                'sent_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (\Throwable $th) {
            $recipient->forceFill([
                'status' => MailRecipientStatusEnum::FAILED->value,
                'last_error' => $th->getMessage(),
            ])->save();

            Log::warning('Bulk mail send failed for one recipient.', [
                'mail_uuid' => $mail->uuid,
                'recipient_email' => $recipient->email,
                'exception' => $th::class,
                'message' => $th->getMessage(),
            ]);
        }
    }

    private function footerHtml(MailCampaign $mail, MailRecipient $recipient, string $unsubscribeUrl): string
    {
        $trackingUrl = URL::signedRoute('mails.track', [$mail->uuid, $recipient->uuid]);

        // No display:none: that's a known spam-filter signature; the 1x1 size already hides it.
        return '<p style="margin-top:24px; font-size:12px; color:#8a8a8a;">'
            .'<a href="'.$unsubscribeUrl.'" style="color:#8a8a8a;">Unsubscribe</a> from these emails.</p>'
            .'<img src="'.$trackingUrl.'" width="1" height="1" alt="">';
    }

    /** `sent` only once every recipient succeeds; otherwise `failed`, so it stays resendable. */
    private function updateMailRollupStatus(MailCampaign $mail): void
    {
        $recipients = $mail->recipients()->get();
        $allSent = $recipients->isNotEmpty() && $recipients->every(fn (MailRecipient $r) => $r->status === MailRecipientStatusEnum::SENT->value);

        if ($allSent) {
            $mail->forceFill([
                'status' => MailStatusEnum::SENT->value,
                'sent_at' => now(),
            ])->save();

            return;
        }

        $mail->forceFill(['status' => MailStatusEnum::FAILED->value])->save();
    }
}
