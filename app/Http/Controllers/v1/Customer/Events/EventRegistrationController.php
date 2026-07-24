<?php

namespace App\Http\Controllers\v1\Customer\Events;

use App\Enums\PaymentMethodEnum;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Events\EventRegistrationListRequest;
use App\Http\Requests\Customer\Events\RegisterForEventRequest;
use App\Http\Resources\Events\EventRegistrationResource;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Events\EventTicketService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group('Customer Events / Registrations', 'Register for events and view my tickets (BRD EVT-02, FEM-07-style receipts).')]
class EventRegistrationController extends Controller
{
    public function __construct(private readonly EventTicketService $eventTicketService) {}

    #[Endpoint('Register for event')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Event UUID.', required: true, example: 'e1f2a3b4-c5d6-47e8-98f9-a0b1c2d3e4f5')]
    #[BodyParam('tickets', 'object[]', 'Ticket types and quantities to purchase.', required: true)]
    #[BodyParam('tickets[].ticket_type_uuid', 'string', 'UUID of the ticket type.', required: true, example: 'f2a3b4c5-d6e7-48f9-a9b0-c1d2e3f4a5b6')]
    #[BodyParam('tickets[].quantity', 'int', 'Number of this ticket type to buy.', required: true, example: 2)]
    #[BodyParam('payment_method', 'string', 'Payment method for this charge (ignored for fully free registrations).', required: true, example: 'card', enum: PaymentMethodEnum::class)]
    #[Response(status: 201, content: [
        'error' => false,
        'message' => 'Registration created.',
        'data' => [
            'registration' => ['uuid' => 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6', 'status' => 'pending'],
            'authorization_url' => 'https://checkout.paystack.com/abc123',
            'reference' => 'TIX_ABCDEFGHIJKLMNOPQRST',
        ],
    ], description: 'Registration created; complete payment at authorization_url (or already completed, for free tickets).')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'Registration is closed for this event.',
        'data' => null,
    ], description: 'Event is not open for registration, or a ticket type is sold out/unavailable.')]
    public function store(RegisterForEventRequest $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $result = $this->eventTicketService->register($user, $uuid, $request->validated(), $request);

            return JsonResponser::send(false, 'Registration created.', [
                'registration' => EventRegistrationResource::make($result['registration']),
                'authorization_url' => $result['authorization_url'],
                'reference' => $result['reference'],
            ], 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Events\EventRegistrationController@store');
        }
    }

    #[Endpoint('My tickets')]
    #[Authenticated]
    #[QueryParam('status', 'string', 'Filter by registration status.', required: false, example: 'completed')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Results per page (max 100).', required: false, example: 15)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Registrations retrieved.',
        'data' => ['current_page' => 1, 'data' => [], 'per_page' => 15, 'total' => 0],
    ], description: 'Paginated list of the customer\'s event registrations.')]
    public function index(EventRegistrationListRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $paginator = $this->eventTicketService->paginateRegistrationsForUser($user, $request->validated());

            return JsonResponser::send(false, 'Registrations retrieved.', $this->paginatedPayload($paginator), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Events\EventRegistrationController@index');
        }
    }

    #[Endpoint('View ticket')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Registration UUID.', required: true, example: 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Registration retrieved.',
        'data' => ['uuid' => 'a1b2c3d4-e5f6-47a8-89b0-c1d2e3f4a5b6', 'status' => 'completed'],
    ], description: 'Registration found and belongs to the authenticated customer.')]
    #[Response(status: 404, content: [
        'error' => true,
        'message' => 'Registration not found.',
        'data' => null,
    ], description: 'No registration with that UUID belongs to the authenticated customer.')]
    public function show(Request $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $registration = $this->eventTicketService->findRegistrationForUser($user, $uuid);

            return JsonResponser::send(false, 'Registration retrieved.', EventRegistrationResource::make($registration), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Events\EventRegistrationController@show');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator): array
    {
        $payload = $paginator->toArray();
        $payload['data'] = EventRegistrationResource::collection($paginator)->resolve();

        return $payload;
    }

    private function requireCustomer(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403, 'Forbidden.');
        }

        return $user;
    }
}
