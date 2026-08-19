<?php

namespace App\Http\Controllers\v1\Admin\Events;

use App\Helpers\GeneralHelper;
use App\Helpers\PDFReportHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DateRangeStatsRequest;
use App\Http\Requests\Admin\Events\CreateEventRequest;
use App\Http\Requests\Admin\Events\EventListRequest;
use App\Http\Requests\Admin\Events\EventReportRequest;
use App\Http\Requests\Admin\Events\EventTicketSalesListRequest;
use App\Http\Requests\Admin\Events\EventWaitlistListRequest;
use App\Http\Requests\Admin\Events\UpdateEventRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Http\Resources\Admin\Events\EventAdminResource;
use App\Http\Resources\Admin\Events\EventDetailResource;
use App\Http\Resources\Admin\Events\EventTicketSaleResource;
use App\Http\Resources\Admin\Events\EventWaitlistEntryResource;
use App\Models\Admin;
use App\Models\EventRegistration;
use App\Models\EventWaitlistEntry;
use App\Responser\JsonResponser;
use App\Services\Events\AdminEventService;
use App\Support\ListingQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Admin / Events
 *
 * Endpoints for admins to create, manage, and report on events: ticket types, ticket sales,
 * the waitlist, reminders, and PDF/CSV exports. All endpoints require a Sanctum bearer token
 * for an admin with the relevant `events.*` permission.
 */
class EventController extends Controller
{
    public function __construct(private readonly AdminEventService $eventService) {}

    /**
     * Create event
     *
     * Creates a new event along with its ticket types. The event is published immediately;
     * capacity is derived from the sum of the ticket type quantities.
     *
     * @authenticated
     *
     * @bodyParam title string required Event title. Example: NHEF Alumni Homecoming 2026
     * @bodyParam description string Event description. Example: An evening of reconnection and giving back.
     * @bodyParam starts_at string required Start date/time (ISO 8601 or any parseable date string). Example: 2026-12-05T18:00:00Z
     * @bodyParam ends_at string End date/time; must be on/after `starts_at`. Example: 2026-12-05T22:00:00Z
     * @bodyParam registration_ends_at string When registration closes; must be on/before `starts_at`. Example: 2026-12-01T23:59:59Z
     * @bodyParam banner_image file required Banner image (JPG, PNG, or WEBP, max 5MB). May alternatively be a base64 data-URI string or an existing image URL.
     * @bodyParam event_type string required One of `virtual`, `physical`. Example: physical
     * @bodyParam virtual_link string Meeting URL; required when `event_type` is `virtual`. Example: https://meet.google.com/abc-defg-hij
     * @bodyParam venue_name string Venue name; required when `event_type` is `physical`. Example: NHEF Conference Hall
     * @bodyParam venue_address string Venue address; required when `event_type` is `physical`. Example: 12 Alumni Way, Lagos
     * @bodyParam waitlist_enabled boolean Whether to enable a waitlist once tickets sell out. Example: true
     * @bodyParam is_paid boolean required Whether tickets are paid; when false, ticket prices are ignored and treated as free. Example: true
     * @bodyParam tickets object[] required At least one ticket type.
     * @bodyParam tickets[].uuid string Existing ticket type UUID (omit when creating). Example: null
     * @bodyParam tickets[].name string required Ticket type name. Example: Regular
     * @bodyParam tickets[].price numeric Ticket price; required when `is_paid` is true. Example: 5000
     * @bodyParam tickets[].currency string One of the supported currency codes. Example: NGN
     * @bodyParam tickets[].quantity integer required Number of seats for this ticket type. Example: 200
     * @bodyParam tickets[].discount_percentage numeric Discount percentage (0-100). Example: 10
     * @bodyParam tickets[].discount_starts_at string When the discount becomes active. Example: 2026-11-01T00:00:00Z
     * @bodyParam tickets[].discount_ends_at string When the discount ends; must be on/after `discount_starts_at`. Example: 2026-11-15T23:59:59Z
     *
     * @response 201 {
     *   "error": false,
     *   "message": "Event created successfully.",
     *   "data": {
     *     "uuid": "3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f",
     *     "title": "NHEF Alumni Homecoming 2026",
     *     "slug": "nhef-alumni-homecoming-2026",
     *     "description": "An evening of reconnection and giving back.",
     *     "cover_image_url": "https://cdn.nhef.org/events/banners/homecoming.jpg",
     *     "event_type": "physical",
     *     "venue_name": "NHEF Conference Hall",
     *     "venue_address": "12 Alumni Way, Lagos",
     *     "virtual_link": null,
     *     "starts_at": "2026-12-05T18:00:00+00:00",
     *     "ends_at": "2026-12-05T22:00:00+00:00",
     *     "registration_ends_at": "2026-12-01T23:59:59+00:00",
     *     "capacity": 200,
     *     "seats_taken": 0,
     *     "seats_remaining": 200,
     *     "status": "published",
     *     "display_status": "Upcoming",
     *     "waitlist_enabled": true,
     *     "ticket_types": [
     *       {
     *         "uuid": "9a1b2c3d-4e5f-6789-abcd-ef0123456789",
     *         "name": "Regular",
     *         "price": "5000.00",
     *         "price_formatted": "₦5,000.00",
     *         "currency": "NGN",
     *         "quantity": 200,
     *         "quantity_sold": 0,
     *         "quantity_remaining": 200,
     *         "discount_percentage": "10.00",
     *         "discount_starts_at": "2026-11-01T00:00:00+00:00",
     *         "discount_ends_at": "2026-11-15T23:59:59+00:00",
     *         "is_available": true
     *       }
     *     ],
     *     "share_url": "https://nhef.org/events/nhef-alumni-homecoming-2026",
     *     "created_at": "2026-08-19T10:00:00+00:00"
     *   }
     * }
     * @response 422 {
     *   "error": true,
     *   "message": "Please add at least one ticket type.",
     *   "data": {
     *     "tickets": ["Please add at least one ticket type."]
     *   }
     * }
     */
    public function store(CreateEventRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $event = $this->eventService->create($request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Event created successfully.', EventDetailResource::make($event)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@store');
        }
    }

    /**
     * List events
     *
     * Paginated, filterable list of events for the admin events table.
     *
     * @authenticated
     *
     * @queryParam search string Free-text search on event title. Example: homecoming
     * @queryParam period string Relative date window. One of `1day`, `3days`, `7days`, `14days`, `30days`, `3months`, `6months`, `1year`, `lastyear`, `custom`. Example: 30days
     * @queryParam start_date string Range start (required when `period` is `custom`). Example: 2026-01-01
     * @queryParam end_date string Range end (required when `period` is `custom`); must be on/after `start_date`. Example: 2026-12-31
     * @queryParam sort_by string Column to sort by. Currently only `name`. Example: name
     * @queryParam sort_direction string `asc` or `desc`. Example: desc
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Results per page (max 100). Example: 15
     * @queryParam filters.status string Filter by display status (matches each event's `display_status` in the response). One of `draft`, `scheduled`, `ongoing`, `completed`, `cancelled`, `deactivated`, `archived`. `scheduled`/`ongoing`/`completed` are derived from a published event's `starts_at`/`ends_at` against the current time. Example: ongoing
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Events retrieved.",
     *   "data": {
     *     "current_page": 1,
     *     "data": [
     *       {
     *         "uuid": "3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f",
     *         "title": "NHEF Alumni Homecoming 2026",
     *         "slug": "nhef-alumni-homecoming-2026",
     *         "cover_image_url": "https://cdn.nhef.org/events/banners/homecoming.jpg",
     *         "event_type": "physical",
     *         "venue_name": "NHEF Conference Hall",
     *         "venue_address": "12 Alumni Way, Lagos",
     *         "virtual_link": null,
     *         "starts_at": "2026-12-05T18:00:00+00:00",
     *         "ends_at": "2026-12-05T22:00:00+00:00",
     *         "capacity": 200,
     *         "seats_taken": 40,
     *         "seats_remaining": 160,
     *         "status": "published",
     *         "display_status": "Upcoming",
     *         "waitlist_enabled": true,
     *         "ticket_types": [],
     *         "share_url": "https://nhef.org/events/nhef-alumni-homecoming-2026",
     *         "created_at": "2026-08-19T10:00:00+00:00"
     *       }
     *     ],
     *     "first_page_url": "https://api.nhef.org/v1/admin/events?page=1",
     *     "from": 1,
     *     "last_page": 1,
     *     "last_page_url": "https://api.nhef.org/v1/admin/events?page=1",
     *     "next_page_url": null,
     *     "path": "https://api.nhef.org/v1/admin/events",
     *     "per_page": 15,
     *     "prev_page_url": null,
     *     "to": 1,
     *     "total": 1
     *   }
     * }
     */
    public function index(EventListRequest $request)
    {
        try {
            $paginator = $this->eventService->paginateForAdmin($request->validated());

            return JsonResponser::send(false, 'Events retrieved.', $this->paginatedPayload($paginator, EventAdminResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@index');
        }
    }

    /**
     * Event overview stats
     *
     * Counts of events by status bucket, for the events dashboard stat cards.
     *
     * @authenticated
     *
     * @queryParam period string Relative date window applied to event creation date. One of `1day`, `3days`, `7days`, `14days`, `30days`, `3months`, `6months`, `1year`, `lastyear`, `custom`. Example: 30days
     * @queryParam start_date string Range start (required when `period` is `custom`). Example: 2026-01-01
     * @queryParam end_date string Range end (required when `period` is `custom`); must be on/after `start_date`. Example: 2026-12-31
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Event overview retrieved.",
     *   "data": {
     *     "all": 12,
     *     "ongoing": 3,
     *     "completed": 7,
     *     "archived": 2
     *   }
     * }
     */
    public function overview(DateRangeStatsRequest $request)
    {
        try {
            $window = ListingFilterRules::resolveDateWindow($request->validated());
            $overview = $this->eventService->overview($window['start'], $window['end']);

            return JsonResponser::send(false, 'Event overview retrieved.', $overview);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@overview');
        }
    }

    /**
     * Get event
     *
     * Fetches a single event with its ticket types. Not scoped by status, so deactivated and
     * archived events remain viewable.
     *
     * @authenticated
     *
     * @urlParam uuid string required Event UUID. Example: 3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Event retrieved.",
     *   "data": {
     *     "uuid": "3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f",
     *     "title": "NHEF Alumni Homecoming 2026",
     *     "slug": "nhef-alumni-homecoming-2026",
     *     "description": "An evening of reconnection and giving back.",
     *     "cover_image_url": "https://cdn.nhef.org/events/banners/homecoming.jpg",
     *     "event_type": "physical",
     *     "venue_name": "NHEF Conference Hall",
     *     "venue_address": "12 Alumni Way, Lagos",
     *     "virtual_link": null,
     *     "starts_at": "2026-12-05T18:00:00+00:00",
     *     "ends_at": "2026-12-05T22:00:00+00:00",
     *     "registration_ends_at": "2026-12-01T23:59:59+00:00",
     *     "capacity": 200,
     *     "seats_taken": 40,
     *     "seats_remaining": 160,
     *     "status": "published",
     *     "display_status": "Upcoming",
     *     "waitlist_enabled": true,
     *     "ticket_types": [
     *       {
     *         "uuid": "9a1b2c3d-4e5f-6789-abcd-ef0123456789",
     *         "name": "Regular",
     *         "price": "5000.00",
     *         "price_formatted": "₦5,000.00",
     *         "currency": "NGN",
     *         "quantity": 200,
     *         "quantity_sold": 40,
     *         "quantity_remaining": 160,
     *         "discount_percentage": null,
     *         "discount_starts_at": null,
     *         "discount_ends_at": null,
     *         "is_available": true
     *       }
     *     ],
     *     "share_url": "https://nhef.org/events/nhef-alumni-homecoming-2026",
     *     "created_at": "2026-08-19T10:00:00+00:00"
     *   }
     * }
     * @response 404 {
     *   "error": true,
     *   "message": "Event not found.",
     *   "data": null
     * }
     */
    public function show(string $uuid)
    {
        try {
            $event = $this->eventService->findForAdmin($uuid);
            $event->load('ticketTypes');

            return JsonResponser::send(false, 'Event retrieved.', EventDetailResource::make($event)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@show');
        }
    }

    /**
     * Update event
     *
     * Partially updates an event. Only send the fields being changed; omitted fields are left
     * as-is. Sending `tickets` replaces the full set of ticket types for the event.
     *
     * @authenticated
     *
     * @urlParam uuid string required Event UUID. Example: 3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f
     *
     * @bodyParam title string Event title. Example: NHEF Alumni Homecoming 2026 (Updated)
     * @bodyParam description string Event description. Example: An evening of reconnection and giving back.
     * @bodyParam starts_at string Start date/time. Example: 2026-12-05T18:00:00Z
     * @bodyParam ends_at string End date/time; must be on/after `starts_at`. Example: 2026-12-05T22:00:00Z
     * @bodyParam registration_ends_at string When registration closes; must be on/before `starts_at`. Example: 2026-12-01T23:59:59Z
     * @bodyParam banner_image file Banner image (JPG, PNG, or WEBP, max 5MB). May alternatively be a base64 data-URI string or an existing image URL.
     * @bodyParam event_type string One of `virtual`, `physical`. Example: physical
     * @bodyParam virtual_link string Meeting URL; required when `event_type` is `virtual`. Example: https://meet.google.com/abc-defg-hij
     * @bodyParam venue_name string Venue name; required when `event_type` is `physical`. Example: NHEF Conference Hall
     * @bodyParam venue_address string Venue address; required when `event_type` is `physical`. Example: 12 Alumni Way, Lagos
     * @bodyParam waitlist_enabled boolean Whether to enable a waitlist once tickets sell out. Example: true
     * @bodyParam is_paid boolean Whether tickets are paid. Example: true
     * @bodyParam tickets object[] Full replacement set of ticket types; when provided, all existing ticket types are synced to match.
     * @bodyParam tickets[].uuid string Existing ticket type UUID to update in place; omit to create a new one. Example: 9a1b2c3d-4e5f-6789-abcd-ef0123456789
     * @bodyParam tickets[].name string required Ticket type name. Example: VIP
     * @bodyParam tickets[].price numeric Ticket price; required when `is_paid` is true. Example: 15000
     * @bodyParam tickets[].currency string One of the supported currency codes. Example: NGN
     * @bodyParam tickets[].quantity integer required Number of seats for this ticket type. Example: 50
     * @bodyParam tickets[].discount_percentage numeric Discount percentage (0-100). Example: 10
     * @bodyParam tickets[].discount_starts_at string When the discount becomes active. Example: 2026-11-01T00:00:00Z
     * @bodyParam tickets[].discount_ends_at string When the discount ends; must be on/after `discount_starts_at`. Example: 2026-11-15T23:59:59Z
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Event updated.",
     *   "data": {
     *     "uuid": "3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f",
     *     "title": "NHEF Alumni Homecoming 2026 (Updated)",
     *     "slug": "nhef-alumni-homecoming-2026",
     *     "description": "An evening of reconnection and giving back.",
     *     "cover_image_url": "https://cdn.nhef.org/events/banners/homecoming.jpg",
     *     "event_type": "physical",
     *     "venue_name": "NHEF Conference Hall",
     *     "venue_address": "12 Alumni Way, Lagos",
     *     "virtual_link": null,
     *     "starts_at": "2026-12-05T18:00:00+00:00",
     *     "ends_at": "2026-12-05T22:00:00+00:00",
     *     "registration_ends_at": "2026-12-01T23:59:59+00:00",
     *     "capacity": 200,
     *     "seats_taken": 40,
     *     "seats_remaining": 160,
     *     "status": "published",
     *     "display_status": "Upcoming",
     *     "waitlist_enabled": true,
     *     "ticket_types": [],
     *     "share_url": "https://nhef.org/events/nhef-alumni-homecoming-2026",
     *     "created_at": "2026-08-19T10:00:00+00:00"
     *   }
     * }
     * @response 404 {
     *   "error": true,
     *   "message": "Event not found.",
     *   "data": null
     * }
     */
    public function update(UpdateEventRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $event = $this->eventService->update($uuid, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Event updated.', EventDetailResource::make($event)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@update');
        }
    }

    /**
     * Deactivate event
     *
     * Hides the event from donors/guests and suspends ticket sales. Reversible via reactivate.
     * Fails if the event is already deactivated or archived.
     *
     * @authenticated
     *
     * @urlParam uuid string required Event UUID. Example: 3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Event deactivated.",
     *   "data": {
     *     "uuid": "3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f",
     *     "title": "NHEF Alumni Homecoming 2026",
     *     "slug": "nhef-alumni-homecoming-2026",
     *     "cover_image_url": "https://cdn.nhef.org/events/banners/homecoming.jpg",
     *     "event_type": "physical",
     *     "venue_name": "NHEF Conference Hall",
     *     "venue_address": "12 Alumni Way, Lagos",
     *     "virtual_link": null,
     *     "starts_at": "2026-12-05T18:00:00+00:00",
     *     "ends_at": "2026-12-05T22:00:00+00:00",
     *     "capacity": 200,
     *     "seats_taken": 40,
     *     "seats_remaining": 160,
     *     "status": "deactivated",
     *     "display_status": "Deactivated",
     *     "waitlist_enabled": true,
     *     "ticket_types": [],
     *     "share_url": "https://nhef.org/events/nhef-alumni-homecoming-2026",
     *     "created_at": "2026-08-19T10:00:00+00:00"
     *   }
     * }
     * @response 422 {
     *   "error": true,
     *   "message": "Event is already deactivated or archived.",
     *   "data": null
     * }
     */
    public function deactivate(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $event = $this->eventService->deactivate($uuid, $admin, $request);

            return JsonResponser::send(false, 'Event deactivated.', EventAdminResource::make($event)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@deactivate');
        }
    }

    /**
     * Reactivate event
     *
     * Restores a deactivated event back to `published`. Fails if the event is not currently
     * deactivated.
     *
     * @authenticated
     *
     * @urlParam uuid string required Event UUID. Example: 3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Event reactivated.",
     *   "data": {
     *     "uuid": "3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f",
     *     "title": "NHEF Alumni Homecoming 2026",
     *     "slug": "nhef-alumni-homecoming-2026",
     *     "cover_image_url": "https://cdn.nhef.org/events/banners/homecoming.jpg",
     *     "event_type": "physical",
     *     "venue_name": "NHEF Conference Hall",
     *     "venue_address": "12 Alumni Way, Lagos",
     *     "virtual_link": null,
     *     "starts_at": "2026-12-05T18:00:00+00:00",
     *     "ends_at": "2026-12-05T22:00:00+00:00",
     *     "capacity": 200,
     *     "seats_taken": 40,
     *     "seats_remaining": 160,
     *     "status": "published",
     *     "display_status": "Upcoming",
     *     "waitlist_enabled": true,
     *     "ticket_types": [],
     *     "share_url": "https://nhef.org/events/nhef-alumni-homecoming-2026",
     *     "created_at": "2026-08-19T10:00:00+00:00"
     *   }
     * }
     * @response 422 {
     *   "error": true,
     *   "message": "Only a deactivated event can be reactivated.",
     *   "data": null
     * }
     */
    public function reactivate(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $event = $this->eventService->reactivate($uuid, $admin, $request);

            return JsonResponser::send(false, 'Event reactivated.', EventAdminResource::make($event)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@reactivate');
        }
    }

    /**
     * Archive event
     *
     * Moves the event out of active listings for reporting/record-keeping. Fails if the event
     * is already archived.
     *
     * @authenticated
     *
     * @urlParam uuid string required Event UUID. Example: 3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Event archived.",
     *   "data": {
     *     "uuid": "3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f",
     *     "title": "NHEF Alumni Homecoming 2026",
     *     "slug": "nhef-alumni-homecoming-2026",
     *     "cover_image_url": "https://cdn.nhef.org/events/banners/homecoming.jpg",
     *     "event_type": "physical",
     *     "venue_name": "NHEF Conference Hall",
     *     "venue_address": "12 Alumni Way, Lagos",
     *     "virtual_link": null,
     *     "starts_at": "2026-12-05T18:00:00+00:00",
     *     "ends_at": "2026-12-05T22:00:00+00:00",
     *     "capacity": 200,
     *     "seats_taken": 40,
     *     "seats_remaining": 160,
     *     "status": "archived",
     *     "display_status": "Archived",
     *     "waitlist_enabled": true,
     *     "ticket_types": [],
     *     "share_url": "https://nhef.org/events/nhef-alumni-homecoming-2026",
     *     "created_at": "2026-08-19T10:00:00+00:00"
     *   }
     * }
     * @response 422 {
     *   "error": true,
     *   "message": "Event is already archived.",
     *   "data": null
     * }
     */
    public function archive(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $event = $this->eventService->archive($uuid, $admin, $request);

            return JsonResponser::send(false, 'Event archived.', EventAdminResource::make($event)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@archive');
        }
    }

    /**
     * Send event reminder
     *
     * Queues a reminder to everyone with a completed registration for the event: guests get a
     * direct email, registered users get a database + email notification. Delivery happens
     * asynchronously on the queue, so `recipient_count` reflects how many reminders were
     * queued, not confirmation that they've been delivered yet.
     *
     * @authenticated
     *
     * @urlParam uuid string required Event UUID. Example: 3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Event reminder queued.",
     *   "data": {
     *     "recipient_count": 42
     *   }
     * }
     */
    public function sendReminder(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $queuedCount = $this->eventService->sendReminder($uuid, $admin, $request);

            return JsonResponser::send(false, 'Event reminder queued.', ['recipient_count' => $queuedCount]);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@sendReminder');
        }
    }

    /**
     * Download event report
     *
     * Streams a summary of each ticket type's price, allocation, sold count, remaining count,
     * and discount, as either a portrait PDF (default) or a CSV. Response is a file download
     * (`application/pdf` or `text/csv`), not JSON.
     *
     * @authenticated
     *
     * @urlParam uuid string required Event UUID. Example: 3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f
     * @queryParam export string Export format. One of `csv`, `pdf`. Defaults to `pdf`. Example: pdf
     */
    public function downloadReport(EventReportRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $format = $request->validated('export') ?? 'pdf';

            return $this->eventService->downloadReport($uuid, $admin, $request, $format);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@downloadReport');
        }
    }

    /**
     * Event analytics
     *
     * Ticket demand, capacity, sales trend, and per-ticket-type distribution for a single
     * event. `period`/`start_date`/`end_date` scope the sales trend window (defaults to the
     * last 7 days when omitted).
     *
     * @authenticated
     *
     * @urlParam uuid string required Event UUID. Example: 3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f
     * @queryParam period string Relative date window for the sales trend. One of `1day`, `3days`, `7days`, `14days`, `30days`, `3months`, `6months`, `1year`, `lastyear`, `custom`. Example: 7days
     * @queryParam start_date string Range start (required when `period` is `custom`). Example: 2026-08-01
     * @queryParam end_date string Range end (required when `period` is `custom`); must be on/after `start_date`. Example: 2026-08-19
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Event analytics retrieved.",
     *   "data": {
     *     "overview": {
     *       "tickets_sold": 40,
     *       "remaining_tickets": 160,
     *       "capacity_filled_percentage": 20.0
     *     },
     *     "ticket_demand": {
     *       "sold": 40,
     *       "available": 160,
     *       "waitlisted": 5
     *     },
     *     "sales_trend": {
     *       "start_date": "2026-08-13",
     *       "end_date": "2026-08-19",
     *       "points": [
     *         { "date": "2026-08-13", "quantity": 3 },
     *         { "date": "2026-08-14", "quantity": 7 }
     *       ]
     *     },
     *     "ticket_type_distribution": [
     *       { "ticket_type": "Regular", "quantity_sold": 30 },
     *       { "ticket_type": "VIP", "quantity_sold": 10 }
     *     ]
     *   }
     * }
     */
    public function analytics(DateRangeStatsRequest $request, string $uuid)
    {
        try {
            $event = $this->eventService->findForAdmin($uuid);
            $window = ListingFilterRules::resolveDateWindow($request->validated());
            $analytics = $this->eventService->analytics($event, $window['start'], $window['end']);

            return JsonResponser::send(false, 'Event analytics retrieved.', $analytics);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@analytics');
        }
    }

    /**
     * List ticket sales
     *
     * Paginated list of completed ticket sale transactions (one row per checkout) for an
     * event. Pass `export` to instead stream a CSV or PDF file of the same filtered rows.
     *
     * @authenticated
     *
     * @urlParam uuid string required Event UUID. Example: 3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f
     * @queryParam search string Free-text search on attendee name/email. Example: jane
     * @queryParam period string Relative date window on purchase date. One of `1day`, `3days`, `7days`, `14days`, `30days`, `3months`, `6months`, `1year`, `lastyear`, `custom`. Example: 30days
     * @queryParam start_date string Range start (required when `period` is `custom`). Example: 2026-01-01
     * @queryParam end_date string Range end (required when `period` is `custom`); must be on/after `start_date`. Example: 2026-12-31
     * @queryParam sort_by string Column to sort by. Currently only `value`. Example: value
     * @queryParam sort_direction string `asc` or `desc`. Example: desc
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Results per page (max 100). Example: 15
     * @queryParam export string When set, streams a file download instead of JSON. One of `csv`, `pdf`. Example: csv
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Ticket sales retrieved.",
     *   "data": {
     *     "current_page": 1,
     *     "data": [
     *       {
     *         "uuid": "5c6d7e8f-9a0b-1c2d-3e4f-5a6b7c8d9e0f",
     *         "transaction_id": "TRN-5C6D7E8F",
     *         "purchase_date": "2026-08-10T14:32:00+00:00",
     *         "ticket_type": "Regular",
     *         "number_of_tickets": 2,
     *         "amount": "10000.00",
     *         "amount_formatted": "₦10,000.00",
     *         "currency": "NGN",
     *         "status": "completed",
     *         "attendee_name": "Jane Doe",
     *         "attendee_email": "jane.doe@example.com",
     *         "is_guest": false,
     *         "paid_via": "card",
     *         "items": [
     *           {
     *             "uuid": "1a2b3c4d-5e6f-7890-abcd-ef1234567890",
     *             "ticket_type": "Regular",
     *             "quantity": 2,
     *             "unit_price": "5000.00",
     *             "unit_price_formatted": "₦5,000.00",
     *             "subtotal": "10000.00",
     *             "subtotal_formatted": "₦10,000.00"
     *           }
     *         ]
     *       }
     *     ],
     *     "first_page_url": "https://api.nhef.org/v1/admin/events/3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f/ticket-sales?page=1",
     *     "from": 1,
     *     "last_page": 1,
     *     "last_page_url": "https://api.nhef.org/v1/admin/events/3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f/ticket-sales?page=1",
     *     "next_page_url": null,
     *     "path": "https://api.nhef.org/v1/admin/events/3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f/ticket-sales",
     *     "per_page": 15,
     *     "prev_page_url": null,
     *     "to": 1,
     *     "total": 1
     *   }
     * }
     */
    public function ticketSales(EventTicketSalesListRequest $request, string $uuid)
    {
        try {
            $event = $this->eventService->findForAdmin($uuid);
            $export = $request->validated('export');

            if ($export === 'csv') {
                return $this->respondTicketSalesCsv($event->uuid, $request->validated());
            }

            if ($export === 'pdf') {
                return $this->respondTicketSalesPdf($event->uuid, $request->validated());
            }

            $paginator = $this->eventService->paginateTicketSales($event, $request->validated());

            return JsonResponser::send(false, 'Ticket sales retrieved.', $this->paginatedPayload($paginator, EventTicketSaleResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@ticketSales');
        }
    }

    /**
     * Get ticket sale
     *
     * Fetches a single ticket sale transaction for an event.
     *
     * @authenticated
     *
     * @urlParam uuid string required Event UUID. Example: 3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f
     * @urlParam saleUuid string required Ticket sale (event registration) UUID. Example: 5c6d7e8f-9a0b-1c2d-3e4f-5a6b7c8d9e0f
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Ticket sale retrieved.",
     *   "data": {
     *     "uuid": "5c6d7e8f-9a0b-1c2d-3e4f-5a6b7c8d9e0f",
     *     "transaction_id": "TRN-5C6D7E8F",
     *     "purchase_date": "2026-08-10T14:32:00+00:00",
     *     "ticket_type": "Regular",
     *     "number_of_tickets": 2,
     *     "amount": "10000.00",
     *     "amount_formatted": "₦10,000.00",
     *     "currency": "NGN",
     *     "status": "completed",
     *     "attendee_name": "Jane Doe",
     *     "attendee_email": "jane.doe@example.com",
     *     "is_guest": false,
     *     "paid_via": "card",
     *     "items": [
     *       {
     *         "uuid": "1a2b3c4d-5e6f-7890-abcd-ef1234567890",
     *         "ticket_type": "Regular",
     *         "quantity": 2,
     *         "unit_price": "5000.00",
     *         "unit_price_formatted": "₦5,000.00",
     *         "subtotal": "10000.00",
     *         "subtotal_formatted": "₦10,000.00"
     *       }
     *     ]
     *   }
     * }
     * @response 404 {
     *   "error": true,
     *   "message": "Ticket sale not found.",
     *   "data": null
     * }
     */
    public function ticketSale(string $uuid, string $saleUuid)
    {
        try {
            $event = $this->eventService->findForAdmin($uuid);
            $sale = $this->eventService->findTicketSale($event, $saleUuid);

            return JsonResponser::send(false, 'Ticket sale retrieved.', EventTicketSaleResource::make($sale)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@ticketSale');
        }
    }

    /**
     * List waitlist entries
     *
     * Paginated list of waitlist entries for an event. Pass `export` to instead stream a CSV
     * or PDF file of the same filtered rows.
     *
     * @authenticated
     *
     * @urlParam uuid string required Event UUID. Example: 3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f
     * @queryParam search string Free-text search on attendee name/email. Example: john
     * @queryParam period string Relative date window on the date joined the waitlist. One of `1day`, `3days`, `7days`, `14days`, `30days`, `3months`, `6months`, `1year`, `lastyear`, `custom`. Example: 30days
     * @queryParam start_date string Range start (required when `period` is `custom`). Example: 2026-01-01
     * @queryParam end_date string Range end (required when `period` is `custom`); must be on/after `start_date`. Example: 2026-12-31
     * @queryParam sort_by string Column to sort by. Currently only `value`. Example: value
     * @queryParam sort_direction string `asc` or `desc`. Example: desc
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Results per page (max 100). Example: 15
     * @queryParam export string When set, streams a file download instead of JSON. One of `csv`, `pdf`. Example: csv
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Waitlist retrieved.",
     *   "data": {
     *     "current_page": 1,
     *     "data": [
     *       {
     *         "uuid": "2b3c4d5e-6f70-8192-a3b4-c5d6e7f80912",
     *         "waitlist_id": "WA-2B3C4D",
     *         "attendee_name": "John Smith",
     *         "attendee_email": "john.smith@example.com",
     *         "is_guest": true,
     *         "guest_phone": "+2348012345678",
     *         "ticket_requested": "Regular",
     *         "quantity_requested": 1,
     *         "waitlist_position": 3,
     *         "projected_value": "5000.00",
     *         "projected_value_formatted": "₦5,000.00",
     *         "status": "pending",
     *         "date_joined_waitlist": "2026-08-12T09:15:00+00:00"
     *       }
     *     ],
     *     "first_page_url": "https://api.nhef.org/v1/admin/events/3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f/waitlist?page=1",
     *     "from": 1,
     *     "last_page": 1,
     *     "last_page_url": "https://api.nhef.org/v1/admin/events/3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f/waitlist?page=1",
     *     "next_page_url": null,
     *     "path": "https://api.nhef.org/v1/admin/events/3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f/waitlist",
     *     "per_page": 15,
     *     "prev_page_url": null,
     *     "to": 1,
     *     "total": 1
     *   }
     * }
     */
    public function waitlist(EventWaitlistListRequest $request, string $uuid)
    {
        try {
            $event = $this->eventService->findForAdmin($uuid);
            $export = $request->validated('export');

            if ($export === 'csv') {
                return $this->respondWaitlistCsv($event->uuid, $request->validated());
            }

            if ($export === 'pdf') {
                return $this->respondWaitlistPdf($event->uuid, $request->validated());
            }

            $paginator = $this->eventService->paginateWaitlist($event, $request->validated());

            return JsonResponser::send(false, 'Waitlist retrieved.', $this->paginatedPayload($paginator, EventWaitlistEntryResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@waitlist');
        }
    }

    /**
     * Get waitlist entry
     *
     * Fetches a single waitlist entry for an event.
     *
     * @authenticated
     *
     * @urlParam uuid string required Event UUID. Example: 3f7b6b2a-1c3e-4e9a-9c2d-7a8b9c0d1e2f
     * @urlParam entryUuid string required Waitlist entry UUID. Example: 2b3c4d5e-6f70-8192-a3b4-c5d6e7f80912
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Waitlist entry retrieved.",
     *   "data": {
     *     "uuid": "2b3c4d5e-6f70-8192-a3b4-c5d6e7f80912",
     *     "waitlist_id": "WA-2B3C4D",
     *     "attendee_name": "John Smith",
     *     "attendee_email": "john.smith@example.com",
     *     "is_guest": true,
     *     "guest_phone": "+2348012345678",
     *     "ticket_requested": "Regular",
     *     "quantity_requested": 1,
     *     "waitlist_position": 3,
     *     "projected_value": "5000.00",
     *     "projected_value_formatted": "₦5,000.00",
     *     "status": "pending",
     *     "date_joined_waitlist": "2026-08-12T09:15:00+00:00"
     *   }
     * }
     * @response 404 {
     *   "error": true,
     *   "message": "Waitlist entry not found.",
     *   "data": null
     * }
     */
    public function waitlistEntry(string $uuid, string $entryUuid)
    {
        try {
            $event = $this->eventService->findForAdmin($uuid);
            $entry = $this->eventService->findWaitlistEntry($event, $entryUuid);

            return JsonResponser::send(false, 'Waitlist entry retrieved.', EventWaitlistEntryResource::make($entry)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Events\EventController@waitlistEntry');
        }
    }

    private function respondTicketSalesCsv(string $eventUuid, array $filters): StreamedResponse
    {
        $event = $this->eventService->findForAdmin($eventUuid);
        [$rows, $truncated] = $this->eventService->exportTicketSales($event, $filters);
        $filename = 'ticket-sales-'.$event->slug.'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Transaction ID', 'Purchase Date', 'Ticket Type', 'No. of Ticket', 'Ticket Value', 'Status', 'Attendee', 'Email']);

            /** @var EventRegistration $registration */
            foreach ($rows as $registration) {
                $ticketTypeNames = $registration->items->map(fn ($item) => $item->ticketType?->name)->filter()->unique()->implode(', ');
                fputcsv($out, [
                    'TRN-'.strtoupper(substr($registration->uuid, 0, 8)),
                    $registration->completed_at?->toIso8601String(),
                    $ticketTypeNames,
                    (int) $registration->items->sum('quantity'),
                    (string) $registration->amount,
                    $registration->status,
                    $registration->attendeeName(),
                    $registration->attendeeEmail(),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Export-Truncated' => $truncated ? '1' : '0',
        ]);
    }

    private function respondTicketSalesPdf(string $eventUuid, array $filters)
    {
        $event = $this->eventService->findForAdmin($eventUuid);
        [$rows, $truncated] = $this->eventService->exportTicketSales($event, $filters);
        $listing = ListingQuery::fromValidated($filters);

        $headings = ['Transaction ID', 'Purchase Date', 'Ticket Type', 'No. of Ticket', 'Ticket Value', 'Status'];
        $tableRows = $rows->map(fn (EventRegistration $registration): array => [
            'TRN-'.strtoupper(substr($registration->uuid, 0, 8)),
            $registration->completed_at?->format('Y-m-d H:i') ?? '',
            $registration->items->map(fn ($item) => $item->ticketType?->name)->filter()->unique()->implode(', '),
            (string) $registration->items->sum('quantity'),
            (string) $registration->amount,
            $registration->status,
        ]);

        return app(PDFReportHelper::class)->download(
            rows: $tableRows,
            headings: $headings,
            title: 'Ticket sales - '.$event->title,
            filename: 'ticket-sales-'.$event->slug.'-'.now()->format('Y-m-d-His').'.pdf',
            orientation: 'landscape',
            periodStart: $listing->startDate?->toDateString() ?? 'All dates',
            periodEnd: $listing->endDate?->toDateString() ?? 'All dates',
            truncated: $truncated,
            includedRows: $tableRows->count(),
        );
    }

    private function respondWaitlistCsv(string $eventUuid, array $filters): StreamedResponse
    {
        $event = $this->eventService->findForAdmin($eventUuid);
        [$rows, $truncated] = $this->eventService->exportWaitlist($event, $filters);
        $filename = 'waitlist-'.$event->slug.'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Waitlist ID', 'Attendee Name', 'Email Address', 'Ticket Requested', 'No. of Ticket', 'Waitlist Position', 'Status']);

            /** @var EventWaitlistEntry $entry */
            foreach ($rows as $entry) {
                fputcsv($out, [
                    'WA-'.strtoupper(substr($entry->uuid, 0, 6)),
                    $entry->attendeeName(),
                    $entry->attendeeEmail(),
                    $entry->ticketType?->name,
                    $entry->quantity_requested,
                    $entry->position,
                    $entry->status,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Export-Truncated' => $truncated ? '1' : '0',
        ]);
    }

    private function respondWaitlistPdf(string $eventUuid, array $filters)
    {
        $event = $this->eventService->findForAdmin($eventUuid);
        [$rows, $truncated] = $this->eventService->exportWaitlist($event, $filters);
        $listing = ListingQuery::fromValidated($filters);

        $headings = ['Waitlist ID', 'Attendee Name', 'Email Address', 'Ticket Requested', 'No. of Ticket', 'Position', 'Status'];
        $tableRows = $rows->map(fn (EventWaitlistEntry $entry): array => [
            'WA-'.strtoupper(substr($entry->uuid, 0, 6)),
            $entry->attendeeName(),
            $entry->attendeeEmail(),
            (string) $entry->ticketType?->name,
            (string) $entry->quantity_requested,
            (string) $entry->position,
            $entry->status,
        ]);

        return app(PDFReportHelper::class)->download(
            rows: $tableRows,
            headings: $headings,
            title: 'Waitlist - '.$event->title,
            filename: 'waitlist-'.$event->slug.'-'.now()->format('Y-m-d-His').'.pdf',
            orientation: 'landscape',
            periodStart: $listing->startDate?->toDateString() ?? 'All dates',
            periodEnd: $listing->endDate?->toDateString() ?? 'All dates',
            truncated: $truncated,
            includedRows: $tableRows->count(),
        );
    }

    /**
     * @param  class-string<JsonResource>  $resourceClass
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator, string $resourceClass): array
    {
        $payload = $paginator->toArray();
        /** @var AnonymousResourceCollection $resource */
        $resource = $resourceClass::collection($paginator);
        $payload['data'] = $resource->resolve();

        return $payload;
    }

    private function requireAdmin(Request $request): Admin
    {
        $admin = $request->user();
        if (! $admin instanceof Admin) {
            abort(403, 'Forbidden.');
        }

        return $admin;
    }
}
