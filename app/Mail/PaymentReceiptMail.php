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
 * Donation acknowledgement email (BRD FEM-07), for guest donors who have no account to
 * receive an in-app notification; see PledgeService::notifyPaymentSucceeded().
 */
class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly Theme $mailTheme,
        public readonly string $campaignTitle,
        public readonly string $amountFormatted,
        public readonly string $reference,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment received - '.$this->campaignTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.donations.payment-receipt',
            with: [
                'recipientName' => $this->recipientName,
                'campaignTitle' => $this->campaignTitle,
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
