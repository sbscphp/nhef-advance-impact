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
 * Event reminder email sent to guest registrants (BRD EVT-01 "Send Event Reminder"), for
 * attendees who have no account to receive an in-app notification; see
 * AdminEventService::sendReminder().
 */
class EventReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly Theme $mailTheme,
        public readonly string $eventTitle,
        public readonly string $eventDateFormatted,
        public readonly string $eventLocation,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reminder - '.$this->eventTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.events.reminder',
            with: [
                'recipientName' => $this->recipientName,
                'eventTitle' => $this->eventTitle,
                'eventDateFormatted' => $this->eventDateFormatted,
                'eventLocation' => $this->eventLocation,
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
