<?php

namespace App\Mail;

use App\Models\Theme;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/** Reuses the campaign message view; a bulk Communications mail is structurally the same email. */
class BulkCampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly Theme $mailTheme,
        public readonly string $messageSubject,
        public readonly string $bodyHtml,
        public readonly string $unsubscribeUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->messageSubject,
        );
    }

    /**
     * List-Unsubscribe/List-Unsubscribe-Post let Gmail/Yahoo show their native one-click
     * unsubscribe control and factor into bulk-sender trust scoring; missing it hurts inbox
     * placement for exactly the kind of mail this class sends.
     */
    public function headers(): Headers
    {
        return new Headers(
            text: [
                'List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.campaign.message',
            with: [
                'campaignName' => $this->messageSubject,
                'recipientName' => $this->recipientName,
                'bodyHtml' => $this->bodyHtml,
                'theme' => $this->mailTheme,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
