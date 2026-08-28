<?php

namespace App\Jobs;

use App\Enums\ProposalRecipientStatusEnum;
use App\Enums\ProposalStatusEnum;
use App\Mail\ProposalSentMail;
use App\Models\ProposalRecipient;
use App\Models\ProspectProposal;
use App\Models\Theme;
use App\Services\Crm\ProposalService;
use App\Services\Theme\ThemeResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/** Sends one email per recipient (not one email to everyone) so a resend only retries who's still owed a delivery. */
class SendProposalToClientJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $proposalUuid) {}

    public function handle(ProposalService $proposalService, ThemeResolver $themeResolver): void
    {
        $proposal = ProspectProposal::query()
            ->with(['prospect', 'recipients'])
            ->where('uuid', $this->proposalUuid)
            ->first();

        if (! $proposal instanceof ProspectProposal || $proposal->recipients->isEmpty()) {
            return;
        }

        $outstanding = $proposal->recipients->whereIn('status', [
            ProposalRecipientStatusEnum::PENDING->value,
            ProposalRecipientStatusEnum::FAILED->value,
        ]);

        if ($outstanding->isEmpty()) {
            return;
        }

        $theme = $themeResolver->resolveForMail();
        $pdfBytes = $proposalService->buildPdfBytes($proposal);
        $pdfFilename = Str::slug($proposal->title).'.pdf';

        $supportingAttachments = collect($proposal->attachments ?? [])
            ->map(fn (string $url) => ['url' => $url, 'name' => basename((string) parse_url($url, PHP_URL_PATH))])
            ->all();

        foreach ($outstanding as $recipient) {
            $this->sendToOneRecipient($recipient, $proposal, $theme, $pdfBytes, $pdfFilename, $supportingAttachments);
        }

        $this->updateProposalRollupStatus($proposal);
    }

    /**
     * @param  list<array{url: string, name: string}>  $supportingAttachments
     */
    private function sendToOneRecipient(
        ProposalRecipient $recipient,
        ProspectProposal $proposal,
        Theme $theme,
        string $pdfBytes,
        string $pdfFilename,
        array $supportingAttachments,
    ): void {
        $recipient->increment('attempts');
        $recipient->forceFill(['last_attempted_at' => now()])->save();

        try {
            Mail::to($recipient->email)->send(new ProposalSentMail(
                $theme,
                $proposal->send_message_title ?? $proposal->title,
                $proposal->send_message_body ?? '',
                $pdfBytes,
                $pdfFilename,
                $supportingAttachments,
            ));

            $recipient->forceFill([
                'status' => ProposalRecipientStatusEnum::SENT->value,
                'sent_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (\Throwable $th) {
            $recipient->forceFill([
                'status' => ProposalRecipientStatusEnum::FAILED->value,
                'last_error' => $th->getMessage(),
            ])->save();

            Log::warning('Proposal send-to-client email failed for one recipient.', [
                'proposal_uuid' => $proposal->uuid,
                'recipient_email' => $recipient->email,
                'exception' => $th::class,
                'message' => $th->getMessage(),
            ]);
        }
    }

    /** `sent` only once every recipient succeeds; otherwise `failed`, so it stays resendable. */
    private function updateProposalRollupStatus(ProspectProposal $proposal): void
    {
        $recipients = $proposal->recipients()->get();
        $allSent = $recipients->isNotEmpty() && $recipients->every(fn (ProposalRecipient $r) => $r->status === ProposalRecipientStatusEnum::SENT->value);

        if ($allSent) {
            $proposal->forceFill([
                'status' => ProposalStatusEnum::SENT->value,
                'sent_at' => now(),
            ])->save();

            return;
        }

        $proposal->forceFill(['status' => ProposalStatusEnum::FAILED->value])->save();
    }
}
