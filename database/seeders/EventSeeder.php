<?php

namespace Database\Seeders;

use App\Enums\CurrencyEnum;
use App\Enums\EventStatusEnum;
use App\Models\Event;
use App\Models\EventTicketType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Sample published events for local development and Postman testing. Events have no admin
 * CRUD yet (BRD EVT-01) — same precedent as DonorTierSeeder — so this is the only way to get
 * events into the database until that's built.
 *
 * php artisan db:seed --class=EventSeeder
 */
class EventSeeder extends Seeder
{
    public function run(): void
    {
        $events = [
            [
                'title' => 'New Year Event',
                'description' => 'Ring in the new year with fellow alumni — an evening of networking, dinner, and celebration.',
                'venue_name' => 'Lagos Marriott Hotel, Ikeja',
                'venue_address' => '122 Joel Ogunnaike Street, Ikeja GRA, Lagos',
                'starts_at' => now()->addMonth()->setTime(9, 0),
                'ends_at' => now()->addMonth()->setTime(22, 0),
                'capacity' => 400,
                'ticket_types' => [
                    ['name' => 'Standard Ticket', 'price' => 4300, 'quantity' => 300],
                    ['name' => 'VIP Access Ticket', 'price' => 15000, 'quantity' => 100],
                ],
            ],
            [
                'title' => 'Summer Coding Bootcamp',
                'description' => 'A hands-on bootcamp for alumni and students looking to break into software engineering.',
                'venue_name' => 'NHEF Innovation Hub',
                'venue_address' => '45 Herbert Macaulay Way, Yaba, Lagos',
                'starts_at' => now()->addMonths(2)->setTime(10, 0),
                'ends_at' => now()->addMonths(2)->addDays(4)->setTime(17, 0),
                'capacity' => 120,
                'ticket_types' => [
                    ['name' => 'Standard Ticket', 'price' => 8500, 'quantity' => 120],
                ],
            ],
            [
                'title' => 'Art & Craft Workshop',
                'description' => 'A relaxed, creative afternoon workshop open to alumni, partners, and their families.',
                'venue_name' => 'NHEF Community Centre',
                'venue_address' => '10 Admiralty Way, Lekki Phase 1, Lagos',
                'starts_at' => now()->addMonths(3)->setTime(14, 0),
                'ends_at' => now()->addMonths(3)->setTime(18, 0),
                'capacity' => 60,
                'ticket_types' => [
                    ['name' => 'Standard Ticket', 'price' => 3200, 'quantity' => 60],
                ],
            ],
        ];

        foreach ($events as $row) {
            $event = Event::updateOrCreate(
                ['slug' => Str::slug($row['title'])],
                [
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'venue_name' => $row['venue_name'],
                    'venue_address' => $row['venue_address'],
                    'starts_at' => $row['starts_at'],
                    'ends_at' => $row['ends_at'],
                    'registration_ends_at' => $row['starts_at']->clone()->subDay(),
                    'capacity' => $row['capacity'],
                    'status' => EventStatusEnum::PUBLISHED->value,
                ]
            );

            foreach ($row['ticket_types'] as $sortOrder => $ticketType) {
                EventTicketType::updateOrCreate(
                    ['event_id' => $event->id, 'name' => $ticketType['name']],
                    [
                        'price' => $ticketType['price'],
                        'currency' => CurrencyEnum::NGN->value,
                        'quantity' => $ticketType['quantity'],
                        'sales_close_at' => $row['starts_at']->clone()->subDay(),
                        'sort_order' => $sortOrder,
                    ]
                );
            }
        }
    }
}
