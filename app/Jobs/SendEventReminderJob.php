<?php

namespace App\Jobs;

use App\Enums\EventTypeEnum;
use App\Enums\ModuleEnums;
use App\Mail\EventReminderMail;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Notifications\GenericDatabaseNotification;
use App\Services\Notifications\NotificationDispatchService;
use App\Services\Theme\ThemeResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** One reminder for one completed registration: direct email for guests, database+mail notification for registered users. */
class SendEventReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $registrationUuid) {}

    public function handle(NotificationDispatchService $notificationDispatchService): void
    {
        $registration = EventRegistration::query()
            ->with(['event', 'user'])
            ->where('uuid', $this->registrationUuid)
            ->first();

        if (! $registration instanceof EventRegistration || $registration->event === null) {
            return;
        }

        $event = $registration->event;

        if ($registration->isGuest()) {
            $this->sendGuestReminder($registration, $event);

            return;
        }

        $notification = new GenericDatabaseNotification(
            module: ModuleEnums::events->value,
            event: 'event_reminder',
            title: 'Event reminder: '.$event->title,
            message: 'Reminder: "'.$event->title.'" is coming up. We\'ll see you there!',
            meta: ['event_uuid' => $event->uuid],
            sendMail: true,
            mailSubject: 'Reminder - '.$event->title,
        );

        $notificationDispatchService->notifyUsersByUuids([$registration->user->uuid], $notification);
    }

    private function sendGuestReminder(EventRegistration $registration, Event $event): void
    {
        try {
            $theme = app(ThemeResolver::class)->resolveForMail();
            $location = $event->event_type === EventTypeEnum::VIRTUAL->value
                ? (string) $event->virtual_link
                : trim(implode(', ', array_filter([$event->venue_name, $event->venue_address])));

            Mail::to($registration->guest_email)->send(new EventReminderMail(
                $registration->guest_name,
                $theme,
                $event->title,
                $event->starts_at->format('F j, Y g:i A'),
                $location,
            ));
        } catch (\Throwable $th) {
            Log::warning('Guest event reminder email failed.', [
                'registration_uuid' => $registration->uuid,
                'exception' => $th::class,
                'message' => $th->getMessage(),
            ]);
        }
    }
}
