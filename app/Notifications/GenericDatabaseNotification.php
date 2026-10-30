<?php

namespace App\Notifications;

use App\Services\Theme\ThemeResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * In-app (database) notification payload shared by customers ({@see User}) and admins ({@see Admin}).
 * Dispatch synchronously with {@see \Illuminate\Support\Facades\Notification::sendNow} or queued with {@see \Illuminate\Support\Facades\Notification::send}.
 */
class GenericDatabaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $meta
     * @param  list<string>  $tags
     */
    public function __construct(
        public readonly string $module,
        public readonly string $event,
        public readonly string $title,
        public readonly string $message,
        public readonly array $meta = [],
        public readonly ?string $actionUrl = null,
        public readonly ?string $mailSubject = null,
        public readonly ?string $icon = null,
        public readonly string $severity = 'info',
        public readonly array $tags = [],
        public readonly bool $sendMail = false,
        public readonly bool $sendPush = false,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->sendMail && $this->canSendMailTo($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->mailSubject ?? $this->title;

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.notifications.generic', [
                'theme' => app(ThemeResolver::class)->resolveForMail(),
                'headline' => $this->title,
                // Not `message`: Laravel's Mailer injects its own `$message` view variable and would clobber it.
                'bodyText' => $this->message,
                'actionUrl' => $this->actionUrl,
                'actionText' => 'Open',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'module' => $this->module,
            'event' => $this->event,
            'title' => $this->title,
            'message' => $this->message,
            'meta' => $this->meta,
            'action_url' => $this->actionUrl,
            'mail_subject' => $this->mailSubject,
            'icon' => $this->icon,
            'severity' => $this->severity,
            'tags' => $this->tags,
            'send_mail' => $this->sendMail,
            'send_push' => $this->sendPush,
        ];
    }

    private function canSendMailTo(object $notifiable): bool
    {
        return (bool) data_get($notifiable, 'email_notifications_enabled', true);
    }
}
