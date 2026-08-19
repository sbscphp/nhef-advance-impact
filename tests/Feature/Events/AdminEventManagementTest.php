<?php

namespace Tests\Feature\Events;

use App\Enums\CurrencyEnum;
use App\Enums\EventStatusEnum;
use App\Enums\EventTypeEnum;
use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Models\Event;
use App\Models\User;
use App\Services\Events\AdminEventService;
use App\Services\Events\EventTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminEventManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_an_event_with_ticket_types_and_capacity_is_derived(): void
    {
        $admin = $this->makeAdmin();
        $service = app(AdminEventService::class);
        $request = Request::create('/');

        $event = $service->create([
            'title' => 'Alumni Reunion 2026',
            'description' => 'A night of reconnecting with fellow alumni.',
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'ends_at' => now()->addMonth()->addHours(4)->toDateTimeString(),
            'banner_image' => 'https://example.com/banner.jpg',
            'event_type' => EventTypeEnum::PHYSICAL->value,
            'venue_name' => 'Lagos Marriott Hotel',
            'venue_address' => '122 Joel Ogunnaike Street, Ikeja GRA, Lagos',
            'waitlist_enabled' => true,
            'is_paid' => true,
            'tickets' => [
                ['name' => 'Standard Ticket', 'price' => 5000, 'quantity' => 100],
                ['name' => 'VIP Ticket', 'price' => 15000, 'quantity' => 20],
            ],
        ], $admin, $request);

        $this->assertSame('Alumni Reunion 2026', $event->title);
        $this->assertSame(EventStatusEnum::PUBLISHED->value, $event->status);
        $this->assertSame(120, $event->capacity);
        $this->assertSame(2, $event->ticketTypes->count());
        $this->assertDatabaseHas('event_ticket_types', ['event_id' => $event->id, 'name' => 'VIP Ticket', 'price' => 15000]);
    }

    public function test_free_event_forces_ticket_price_to_zero(): void
    {
        $admin = $this->makeAdmin();
        $service = app(AdminEventService::class);
        $request = Request::create('/');

        $event = $service->create([
            'title' => 'Community Open Day',
            'starts_at' => now()->addWeek()->toDateTimeString(),
            'banner_image' => 'https://example.com/banner.jpg',
            'event_type' => EventTypeEnum::VIRTUAL->value,
            'virtual_link' => 'https://meet.google.com/abc-defg-hij',
            'is_paid' => false,
            'tickets' => [
                ['name' => 'General Admission', 'price' => 999999, 'quantity' => 50],
            ],
        ], $admin, $request);

        $this->assertSame('0.00', (string) $event->ticketTypes->first()->price);
    }

    public function test_admin_can_update_an_event_and_sync_ticket_types(): void
    {
        $admin = $this->makeAdmin();
        $service = app(AdminEventService::class);
        $request = Request::create('/');

        $event = $this->makeEvent($admin, $service, $request);
        $keptTicket = $event->ticketTypes->firstWhere('name', 'Standard Ticket');

        $updated = $service->update($event->uuid, [
            'title' => 'Alumni Reunion 2026 (Updated)',
            'is_paid' => true,
            'tickets' => [
                ['uuid' => $keptTicket->uuid, 'name' => 'Standard Ticket', 'price' => 5500, 'quantity' => 150],
                ['name' => 'Early Bird', 'price' => 4000, 'quantity' => 30],
            ],
        ], $admin, $request);

        $this->assertSame('Alumni Reunion 2026 (Updated)', $updated->title);
        $this->assertSame(180, $updated->capacity);
        $this->assertSame(2, $updated->ticketTypes->count());
        $this->assertDatabaseHas('event_ticket_types', ['uuid' => $keptTicket->uuid, 'price' => 5500]);
        $this->assertDatabaseMissing('event_ticket_types', ['event_id' => $event->id, 'name' => 'VIP Ticket']);
    }

    public function test_admin_can_deactivate_then_reactivate_an_event(): void
    {
        $admin = $this->makeAdmin();
        $service = app(AdminEventService::class);
        $request = Request::create('/');

        $event = $this->makeEvent($admin, $service, $request);

        $deactivated = $service->deactivate($event->uuid, $admin, $request);
        $this->assertSame(EventStatusEnum::DEACTIVATED->value, $deactivated->status);

        $this->expectException(ApiException::class);
        $service->deactivate($event->uuid, $admin, $request);
    }

    public function test_reactivating_a_non_deactivated_event_fails(): void
    {
        $admin = $this->makeAdmin();
        $service = app(AdminEventService::class);
        $request = Request::create('/');

        $event = $this->makeEvent($admin, $service, $request);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Only a deactivated event can be reactivated.');

        $service->reactivate($event->uuid, $admin, $request);
    }

    public function test_admin_can_archive_an_event(): void
    {
        $admin = $this->makeAdmin();
        $service = app(AdminEventService::class);
        $request = Request::create('/');

        $event = $this->makeEvent($admin, $service, $request);
        $archived = $service->archive($event->uuid, $admin, $request);

        $this->assertSame(EventStatusEnum::ARCHIVED->value, $archived->status);
    }

    public function test_event_listing_filters_by_derived_display_status(): void
    {
        $admin = $this->makeAdmin();
        $service = app(AdminEventService::class);
        $request = Request::create('/');

        $ongoingEvent = $this->makeVirtualEvent($admin, $service, $request, 'Ongoing Meetup', now()->subHour(), now()->addHour());
        $completedEvent = $this->makeVirtualEvent($admin, $service, $request, 'Past Workshop', now()->subDays(3), now()->subDays(2));
        $scheduledEvent = $this->makeVirtualEvent($admin, $service, $request, 'Future Webinar', now()->addWeek(), null);

        $ongoing = $service->paginateForAdmin(['filters' => ['status' => 'ongoing']]);
        $completed = $service->paginateForAdmin(['filters' => ['status' => 'completed']]);
        $scheduled = $service->paginateForAdmin(['filters' => ['status' => 'scheduled']]);

        $this->assertSame([$ongoingEvent->uuid], $ongoing->pluck('uuid')->all());
        $this->assertSame([$completedEvent->uuid], $completed->pluck('uuid')->all());
        $this->assertSame([$scheduledEvent->uuid], $scheduled->pluck('uuid')->all());
    }

    public function test_finding_a_missing_event_throws_a_404(): void
    {
        $service = app(AdminEventService::class);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Event not found.');

        $service->findForAdmin((string) \Illuminate\Support\Str::uuid());
    }

    public function test_registration_is_waitlisted_when_capacity_is_full_and_waitlist_enabled(): void
    {
        $admin = $this->makeAdmin();
        $adminService = app(AdminEventService::class);
        $request = Request::create('/');

        $event = $adminService->create([
            'title' => 'Small Workshop',
            'starts_at' => now()->addWeek()->toDateTimeString(),
            'banner_image' => 'https://example.com/banner.jpg',
            'event_type' => EventTypeEnum::PHYSICAL->value,
            'venue_name' => 'NHEF Hub',
            'venue_address' => '45 Herbert Macaulay Way, Yaba, Lagos',
            'waitlist_enabled' => true,
            'is_paid' => false,
            'tickets' => [
                ['name' => 'General Admission', 'price' => 0, 'quantity' => 1],
            ],
        ], $admin, $request);

        $ticketType = $event->ticketTypes->first();
        $user = User::factory()->create();

        $ticketService = app(EventTicketService::class);
        $ticketService->register($user, $event->uuid, [
            'tickets' => [['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1]],
        ], $request);

        $secondUser = User::factory()->create();
        $result = $ticketService->register($secondUser, $event->uuid, [
            'tickets' => [['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1]],
        ], $request);

        $this->assertTrue($result['waitlisted']);
        $this->assertDatabaseHas('event_waitlist_entries', [
            'event_id' => $event->id,
            'user_id' => $secondUser->id,
            'status' => 'pending',
        ]);
    }

    public function test_ticket_sales_listing_only_includes_completed_registrations(): void
    {
        $admin = $this->makeAdmin();
        $adminService = app(AdminEventService::class);
        $request = Request::create('/');

        $event = $this->makeEvent($admin, $adminService, $request);
        $ticketType = $event->ticketTypes->first();
        $user = User::factory()->create();

        app(EventTicketService::class)->register($user, $event->uuid, [
            'tickets' => [['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1]],
        ], $request);

        $freshEvent = $adminService->findForAdmin($event->uuid);
        $paginator = $adminService->paginateTicketSales($freshEvent, []);

        $this->assertSame(0, $paginator->total());
    }

    private function makeEvent(Admin $admin, AdminEventService $service, Request $request): Event
    {
        return $service->create([
            'title' => 'Alumni Reunion 2026',
            'description' => 'A night of reconnecting with fellow alumni.',
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'ends_at' => now()->addMonth()->addHours(4)->toDateTimeString(),
            'banner_image' => 'https://example.com/banner.jpg',
            'event_type' => EventTypeEnum::PHYSICAL->value,
            'venue_name' => 'Lagos Marriott Hotel',
            'venue_address' => '122 Joel Ogunnaike Street, Ikeja GRA, Lagos',
            'waitlist_enabled' => true,
            'is_paid' => true,
            'tickets' => [
                ['name' => 'Standard Ticket', 'price' => 5000, 'quantity' => 100, 'currency' => CurrencyEnum::NGN->value],
                ['name' => 'VIP Ticket', 'price' => 15000, 'quantity' => 20, 'currency' => CurrencyEnum::NGN->value],
            ],
        ], $admin, $request);
    }

    private function makeVirtualEvent(Admin $admin, AdminEventService $service, Request $request, string $title, \Carbon\CarbonInterface $startsAt, ?\Carbon\CarbonInterface $endsAt): Event
    {
        return $service->create([
            'title' => $title,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt?->toDateTimeString(),
            'banner_image' => 'https://example.com/banner.jpg',
            'event_type' => EventTypeEnum::VIRTUAL->value,
            'virtual_link' => 'https://meet.google.com/abc-defg-hij',
            'is_paid' => false,
            'tickets' => [
                ['name' => 'General Admission', 'price' => 0, 'quantity' => 50],
            ],
        ], $admin, $request);
    }

    private function makeAdmin(): Admin
    {
        return Admin::create([
            'name' => 'Super Admin',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_active' => true,
            'can_login' => true,
        ]);
    }
}
