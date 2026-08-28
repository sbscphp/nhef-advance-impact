<?php

namespace App\Mail;

use App\Models\Theme;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/** Reuses the campaign message view, same as ProspectMessageMail; `recipientName` is blank since this is sent per recipient. */
class ProposalSentMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{url: string, name: string}>  $supportingAttachments
     */
    public function __construct(
        public readonly Theme $mailTheme,
        public readonly string $messageSubject,
        public readonly string $bodyHtml,
        public readonly string $pdfBytes,
        public readonly string $pdfFilename,
        public readonly array $supportingAttachments,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->messageSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.campaign.message',
            with: [
                'campaignName' => $this->messageSubject,
                'recipientName' => '',
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
        $pdfBytes = $this->pdfBytes;

        $attachments = [
            Attachment::fromData(fn () => $pdfBytes, $this->pdfFilename)->withMime('application/pdf'),
        ];

        foreach ($this->supportingAttachments as $file) {
            $url = $file['url'];
            $attachments[] = Attachment::fromData(fn () => Http::timeout(15)->get($url)->body(), $file['name']);
        }

        return $attachments;
    }
}
