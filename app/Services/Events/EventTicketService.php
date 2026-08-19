<?php

namespace App\Services\Events;

use App\Enums\AuditActionEnum;
use App\Enums\EventRegistrationStatusEnum;
use App\Enums\EventWaitlistStatusEnum;
use App\Enums\ModuleEnums;
use App\Enums\PaymentStatusEnum;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Mail\EventTicketConfirmationMail;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventRegistrationPayment;
use App\Models\EventTicketType;
use App\Models\EventWaitlistEntry;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use App\Repositories\Contracts\Event\EventRegistrationItemRepositoryInterface;
use App\Repositories\Contracts\Event\EventRegistrationPaymentRepositoryInterface;
use App\Repositories\Contracts\Event\EventRegistrationRepositoryInterface;
use App\Repositories\Contracts\Event\EventRepositoryInterface;
use App\Repositories\Contracts\Event\EventTicketTypeRepositoryInterface;
use App\Repositories\Contracts\Event\EventWaitlistEntryRepositoryInterface;
use App\Services\Notifications\NotificationDispatchService;
use App\Services\Settings\PaymentMethodService;
use App\Services\Theme\ThemeResolver;
use App\Services\ThirdParty\Payment\PaymentGatewayService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EventTicketService
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventTicketTypeRepositoryInterface $ticketTypeRepository,
        private readonly EventRegistrationRepositoryInterface $registrationRepository,
        private readonly EventRegistrationItemRepositoryInterface $registrationItemRepository,
        private readonly EventRegistrationPaymentRepositoryInterface $paymentRepository,
        private readonly EventWaitlistEntryRepositoryInterface $waitlistRepository,
        private readonly PaymentGatewayService $paymentGatewayService,
        private readonly NotificationDispatchService $notificationDispatchService,
        private readonly PaymentMethodService $paymentMethodService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateEvents(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->eventRepository->paginatePublished($filters, $perPage);
    }

    public function findEventByUuid(string $uuid): Event
    {
        $event = $this->eventRepository->findPublishedByUuid($uuid);

        if (! $event instanceof Event) {
            throw new ApiException('Event not found.', 404);
        }

        return $event;
    }

    /**
     * @param  ?User  $user  Null for a guest registration (BRD ENT-04); $validated must then
     *                       carry `full_name` and `email` (see GuestRegisterForEventRequest).
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function register(?User $user, string $eventUuid, array $validated, Request $request): array
    {
        $event = $this->findEventByUuid($eventUuid);

        if (! $event->isRegistrationOpen()) {
            throw new ApiException('Registration is closed for this event.', 422);
        }

        $ticketTypes = [];
        $totalQuantity = 0;
        $capacityMessage = null;

        foreach ($validated['tickets'] as $line) {
            $ticketType = $this->ticketTypeRepository->findByUuidForEvent($event, $line['ticket_type_uuid']);

            if (! $ticketType instanceof EventTicketType) {
                throw new ApiException('Ticket type not found.', 404);
            }

            $quantity = (int) $line['quantity'];

            if (! $ticketType->isAvailable()) {
                if (! $event->waitlist_enabled) {
                    throw new ApiException('"'.$ticketType->name.'" is no longer available.', 422);
                }
                $capacityMessage ??= '"'.$ticketType->name.'" is no longer available.';
            }

            $remaining = $ticketType->quantityRemaining();
            if ($remaining !== null && $quantity > $remaining) {
                if (! $event->waitlist_enabled) {
                    throw new ApiException('Only '.$remaining.' "'.$ticketType->name.'" ticket(s) left.', 422);
                }
                $capacityMessage ??= 'Only '.$remaining.' "'.$ticketType->name.'" ticket(s) left.';
            }

            $totalQuantity += $quantity;
            $ticketTypes[] = ['ticketType' => $ticketType, 'quantity' => $quantity];
        }

        $seatsRemaining = $event->seatsRemaining();
        if ($seatsRemaining !== null && $totalQuantity > $seatsRemaining) {
            if (! $event->waitlist_enabled) {
                throw new ApiException('Only '.$seatsRemaining.' seat(s) left for this event.', 422);
            }
            $capacityMessage ??= 'Only '.$seatsRemaining.' seat(s) left for this event.';
        }

        if ($capacityMessage !== null) {
            return $this->addToWaitlist($user, $event, $ticketTypes, $validated, $request);
        }

        return DB::transaction(function () use ($user, $event, $ticketTypes, $validated, $request): array {
            $currency = $ticketTypes[0]['ticketType']->currency;
            $amount = array_reduce(
                $ticketTypes,
                fn (float $carry, array $line): float => $carry + ((float) $line['ticketType']->price * $line['quantity']),
                0.0
            );

            $registration = $this->registrationRepository->create([
                'event_id' => $event->id,
                'user_id' => $user?->id,
                'guest_name' => $user === null ? $validated['full_name'] : null,
                'guest_email' => $user === null ? $validated['email'] : null,
                'currency' => $currency,
                'amount' => $amount,
                'status' => EventRegistrationStatusEnum::PENDING->value,
            ]);

            $this->registrationItemRepository->createMany($registration, array_map(
                fn (array $line): array => [
                    'event_ticket_type_id' => $line['ticketType']->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['ticketType']->price,
                    'currency' => $line['ticketType']->currency,
                    'subtotal' => (float) $line['ticketType']->price * $line['quantity'],
                ],
                $ticketTypes
            ));

            $attendeeName = $user?->displayName() ?? $validated['full_name'];

            GeneralHelper::storeAuditLog(
                $user === null ? UserTypeEnum::GUEST : UserTypeEnum::CUSTOMER,
                AuditActionEnum::EVENT_REGISTRATION_CREATED,
                $request,
                $user?->uuid,
                [
                    'event_uuid' => $event->uuid,
                    'amount' => (string) $amount,
                    'guest' => $user === null,
                    'attendee_email' => $registration->attendeeEmail(),
                ],
                $attendeeName.' registered for "'.$event->title.'".',
                EventRegistration::class,
                $registration->uuid,
                ModuleEnums::events,
                201,
            );

            // Free registrations settle immediately; there's nothing for a payment gateway to do.
            if ((float) $amount <= 0.0) {
                $registration = $this->completeRegistration($registration, $ticketTypes, $event, $request);

                return [
                    'waitlisted' => false,
                    'registration' => $this->registrationRepository->loadFresh($registration, ['user', 'event', 'items.ticketType', 'payments']),
                    'authorization_url' => null,
                    'access_code' => null,
                    'client_secret' => null,
                    'publishable_key' => null,
                    'reference' => null,
                ];
            }

            $initialization = $this->initializePaymentFor($user, $registration, $validated['email'] ?? null);

            return [
                'waitlisted' => false,
                'registration' => $this->registrationRepository->loadFresh($registration, ['user', 'event', 'items.ticketType', 'payments']),
                'authorization_url' => $initialization['authorization_url'],
                'access_code' => $initialization['access_code'],
                'client_secret' => $initialization['client_secret'],
                'publishable_key' => $initialization['publishable_key'],
                'reference' => $initialization['reference'],
            ];
        });
    }

    /**
     * The one place that actually confirms money moved (or, for free tickets, the immediate
     * settlement path in register()). Called from either
     * EventRegistrationPaymentController::verify() or PaystackWebhookController; whichever
     * gets here first, the early-return below makes a second call a harmless no-op.
     *
     * @return array<string, mixed>
     */
    public function verifyPayment(string $reference, Request $request): array
    {
        $payment = $this->paymentRepository->findByReference($reference);

        if (! $payment instanceof EventRegistrationPayment) {
            throw new ApiException('Payment not found.', 404);
        }

        if ($payment->status === PaymentStatusEnum::SUCCESSFUL->value) {
            return ['payment' => $payment, 'registration' => $payment->registration];
        }

        $result = $this->paymentGatewayService->verify($reference, $payment->gateway);

        if ($result['status'] !== 'successful') {
            $payment = $this->paymentRepository->markFailed($payment);
            $registration = $this->registrationRepository->update($payment->registration, [
                'status' => EventRegistrationStatusEnum::FAILED->value,
            ]);

            GeneralHelper::storeAuditLog(
                $payment->user === null ? UserTypeEnum::GUEST : UserTypeEnum::CUSTOMER,
                AuditActionEnum::PAYMENT_FAILED,
                $request,
                $payment->user?->uuid,
                ['reference' => $reference, 'gateway_status' => $result['status'], 'attendee_email' => $registration->attendeeEmail()],
                'Payment for an event registration failed or was not completed.',
                EventRegistrationPayment::class,
                $payment->uuid,
                ModuleEnums::events,
                200,
            );

            return ['payment' => $payment, 'registration' => $registration];
        }

        return DB::transaction(function () use ($payment, $result, $reference, $request): array {
            $payment = $this->paymentRepository->markSuccessful($payment, [
                'paid_at' => now(),
                'card_last_four' => $result['card_last_four'],
                'meta' => array_filter([
                    'channel' => $result['channel'],
                    'gateway_amount' => $result['amount'],
                    'gateway_currency' => $result['currency'],
                ]),
            ]);

            if ($payment->user !== null) {
                $this->paymentMethodService->saveFromAuthorization($payment->user, $result['authorization'], $payment->gateway);
            }

            $registration = $payment->registration->load('items.ticketType');
            $event = $registration->event;

            $registration = $this->completeRegistration(
                $registration,
                $registration->items->map(fn ($item) => ['ticketType' => $item->ticketType, 'quantity' => $item->quantity])->all(),
                $event,
                $request,
            );

            GeneralHelper::storeAuditLog(
                $payment->user === null ? UserTypeEnum::GUEST : UserTypeEnum::CUSTOMER,
                AuditActionEnum::PAYMENT_SUCCEEDED,
                $request,
                $payment->user?->uuid,
                ['reference' => $reference, 'amount' => (string) $payment->amount, 'attendee_email' => $registration->attendeeEmail()],
                'Payment for an event registration succeeded.',
                EventRegistrationPayment::class,
                $payment->uuid,
                ModuleEnums::events,
                200,
            );

            return [
                'payment' => $payment,
                'registration' => $this->registrationRepository->loadFresh($registration, ['user', 'event', 'items.ticketType', 'payments']),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateRegistrationsForUser(User $user, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->registrationRepository->paginateForUser($user->id, $filters, $perPage);
    }

    public function findRegistrationForUser(User $user, string $uuid): EventRegistration
    {
        $registration = $this->registrationRepository->findByUuidForUser($user->id, $uuid);

        if (! $registration instanceof EventRegistration) {
            throw new ApiException('Registration not found.', 404);
        }

        return $registration;
    }

    /**
     * Capacity/quantity was exceeded but {@see Event::waitlist_enabled} is on, so the attempt
     * becomes a waitlist entry instead of a 422. One entry per attempt (not per ticket line):
     * `event_ticket_type_id` records the first requested type, `quantity_requested` the total.
     *
     * @param  list<array{ticketType: EventTicketType, quantity: int}>  $ticketTypes
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function addToWaitlist(?User $user, Event $event, array $ticketTypes, array $validated, Request $request): array
    {
        $totalQuantity = array_sum(array_column($ticketTypes, 'quantity'));
        $currency = $ticketTypes[0]['ticketType']->currency;
        $projectedValue = array_reduce(
            $ticketTypes,
            fn (float $carry, array $line): float => $carry + ((float) $line['ticketType']->price * $line['quantity']),
            0.0
        );

        $entry = $this->waitlistRepository->create([
            'event_id' => $event->id,
            'event_ticket_type_id' => $ticketTypes[0]['ticketType']->id,
            'user_id' => $user?->id,
            'guest_name' => $user === null ? $validated['full_name'] : null,
            'guest_email' => $user === null ? $validated['email'] : null,
            'quantity_requested' => $totalQuantity,
            'currency' => $currency,
            'projected_value' => $projectedValue,
            'position' => $this->waitlistRepository->nextPositionForEvent($event),
            'status' => EventWaitlistStatusEnum::PENDING->value,
        ]);

        $attendeeName = $user?->displayName() ?? $validated['full_name'];

        GeneralHelper::storeAuditLog(
            $user === null ? UserTypeEnum::GUEST : UserTypeEnum::CUSTOMER,
            AuditActionEnum::EVENT_WAITLISTED,
            $request,
            $user?->uuid,
            ['event_uuid' => $event->uuid, 'waitlist_entry_uuid' => $entry->uuid, 'quantity_requested' => $totalQuantity],
            $attendeeName.' was added to the waitlist for "'.$event->title.'".',
            EventWaitlistEntry::class,
            $entry->uuid,
            ModuleEnums::events,
            202,
        );

        return [
            'waitlisted' => true,
            'registration' => null,
            'waitlist_entry_uuid' => $entry->uuid,
            'waitlist_position' => $entry->position,
            'authorization_url' => null,
            'access_code' => null,
            'client_secret' => null,
            'publishable_key' => null,
            'reference' => null,
        ];
    }

    /**
     * Marks a registration COMPLETED, counts its seats/quantities, and sends the confirmation.
     * Shared by the free-ticket short-circuit in register() and the paid path in verifyPayment().
     *
     * @param  list<array{ticketType: EventTicketType, quantity: int}>  $lines
     */
    private function completeRegistration(EventRegistration $registration, array $lines, Event $event, Request $request): EventRegistration
    {
        $totalQuantity = 0;
        foreach ($lines as $line) {
            $this->ticketTypeRepository->incrementQuantitySold($line['ticketType'], $line['quantity']);
            $totalQuantity += $line['quantity'];
        }

        $this->eventRepository->incrementSeatsTaken($event, $totalQuantity);

        $registration = $this->registrationRepository->update($registration, [
            'status' => EventRegistrationStatusEnum::COMPLETED->value,
            'completed_at' => now(),
        ]);

        GeneralHelper::storeAuditLog(
            $registration->isGuest() ? UserTypeEnum::GUEST : UserTypeEnum::CUSTOMER,
            AuditActionEnum::EVENT_REGISTRATION_COMPLETED,
            $request,
            $registration->user?->uuid,
            ['event_uuid' => $event->uuid, 'registration_uuid' => $registration->uuid],
            'Event registration completed.',
            EventRegistration::class,
            $registration->uuid,
            ModuleEnums::events,
            200,
        );

        $this->notifyRegistrationConfirmed($registration, $event);

        return $registration;
    }

    /**
     * @return array{authorization_url: ?string, access_code: ?string, client_secret: ?string, publishable_key: ?string, reference: string, gateway: string}
     */
    private function initializePaymentFor(?User $user, EventRegistration $registration, ?string $guestEmail = null): array
    {
        $reference = 'TIX_'.strtoupper(Str::random(20));
        $email = $user->email ?? $guestEmail;

        $initialization = $this->paymentGatewayService->initialize(
            $reference,
            (string) $registration->amount,
            $registration->currency,
            $email,
            ['event_registration_uuid' => $registration->uuid],
        );

        $this->paymentRepository->create([
            'event_registration_id' => $registration->id,
            'user_id' => $user?->id,
            'amount' => $registration->amount,
            'currency' => $registration->currency,
            'gateway' => $initialization['gateway'],
            'gateway_reference' => $initialization['reference'],
            'status' => PaymentStatusEnum::PENDING->value,
        ]);

        return $initialization;
    }

    /**
     * Guests have no account to hold an in-app notification, so they get a direct email
     * confirmation instead of {@see GenericDatabaseNotification} (which requires a Notifiable model).
     */
    private function notifyRegistrationConfirmed(EventRegistration $registration, Event $event): void
    {
        if ($registration->isGuest()) {
            $this->sendGuestConfirmation($registration, $event);

            return;
        }

        $notification = new GenericDatabaseNotification(
            module: ModuleEnums::events->value,
            event: 'event_registration_confirmed',
            title: 'Event registration confirmed',
            message: 'You\'re registered for "'.$event->title.'". We\'ll see you there!',
            meta: ['event_uuid' => $event->uuid, 'registration_uuid' => $registration->uuid],
            sendMail: true,
            mailSubject: 'Registration confirmed - '.$event->title,
        );

        $this->notificationDispatchService->notifyUsersByUuids([$registration->user->uuid], $notification);
    }

    private function sendGuestConfirmation(EventRegistration $registration, Event $event): void
    {
        try {
            $theme = app(ThemeResolver::class)->resolveForMail();

            Mail::to($registration->guest_email)->send(new EventTicketConfirmationMail(
                $registration->guest_name,
                $theme,
                $event->title,
                Money::format($registration->amount, $registration->currency),
                $registration->uuid,
            ));
        } catch (\Throwable $th) {
            Log::warning('Guest event registration confirmation email failed.', [
                'registration_uuid' => $registration->uuid,
                'exception' => $th::class,
                'message' => $th->getMessage(),
            ]);
        }
    }
}
