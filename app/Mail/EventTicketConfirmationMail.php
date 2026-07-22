<?php

namespace App\Mail;

use App\Models\Theme;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Event registration confirmation email (BRD EVT-02/ENT-04). Used for guest registrants, who
 * have no account to receive an in-app {@see \App\Notifications\GenericDatabaseNotification} —
 * see EventTicketService::notifyRegistrationConfirmed().
 */
class EventTicketConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly Theme $mailTheme,
        public readonly string $eventTitle,
        public readonly string $amountFormatted,
        public readonly string $reference,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Registration confirmed - '.$this->eventTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.events.registration-confirmation',
            with: [
                'recipientName' => $this->recipientName,
                'eventTitle' => $this->eventTitle,
                'amountFormatted' => $this->amountFormatted,
                'reference' => $this->reference,
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
