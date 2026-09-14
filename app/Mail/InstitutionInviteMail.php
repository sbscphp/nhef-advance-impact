<?php

namespace App\Mail;

use App\Models\Theme;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Notifies an institution's contact address that the institution has been added to the platform; see AdminInstitutionService::invite(). */
class InstitutionInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $institutionName,
        public readonly Theme $mailTheme,
        public readonly ?string $inviteMessage = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to NHEF Nexus, '.$this->institutionName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.institutions.invite',
            with: [
                'institutionName' => $this->institutionName,
                'inviteMessage' => $this->inviteMessage,
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
