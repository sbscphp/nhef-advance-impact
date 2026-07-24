<?php

namespace App\Http\Controllers\v1\Customer\Settings;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Settings\PaymentMethodResource;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Settings\PaymentMethodService;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;

/**
 * Saved cards, populated automatically from successful pledge/donation payments whenever
 * Paystack returns a reusable authorization for a logged-in customer (see
 * PaymentMethodService::saveFromAuthorization()). There is no "add a card" endpoint here; a
 * card is only ever saved as a side effect of paying with it.
 */
#[Group('Customer Settings / Payment Methods', 'Manage cards saved from previous pledge/donation payments.')]
class PaymentMethodController extends Controller
{
    public function __construct(private readonly PaymentMethodService $paymentMethodService) {}

    #[Endpoint('List saved payment methods')]
    #[Authenticated]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Payment methods retrieved.',
        'data' => [
            ['uuid' => 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8', 'brand' => 'visa DEBIT', 'last_four' => '3456', 'expires_formatted' => '03/28', 'is_default' => true],
        ],
    ], description: 'Saved payment methods retrieved.')]
    public function index(Request $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $methods = $this->paymentMethodService->listForUser($user);

            return JsonResponser::send(false, 'Payment methods retrieved.', PaymentMethodResource::collection($methods), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Settings\PaymentMethodController@index');
        }
    }

    #[Endpoint('Set default payment method')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Payment method UUID.', required: true, example: 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Default payment method updated.',
        'data' => ['uuid' => 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8', 'is_default' => true],
    ], description: 'Default payment method updated.')]
    #[Response(status: 404, content: [
        'error' => true,
        'message' => 'Payment method not found.',
        'data' => null,
    ], description: 'No saved payment method with that UUID belongs to the authenticated customer.')]
    public function setDefault(Request $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $method = $this->paymentMethodService->setDefault($user, $uuid, $request);

            return JsonResponser::send(false, 'Default payment method updated.', PaymentMethodResource::make($method), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Settings\PaymentMethodController@setDefault');
        }
    }

    #[Endpoint('Delete payment method')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Payment method UUID.', required: true, example: 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Payment method deleted.',
        'data' => null,
    ], description: 'Payment method deleted.')]
    #[Response(status: 404, content: [
        'error' => true,
        'message' => 'Payment method not found.',
        'data' => null,
    ], description: 'No saved payment method with that UUID belongs to the authenticated customer.')]
    public function destroy(Request $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $this->paymentMethodService->delete($user, $uuid, $request);

            return JsonResponser::send(false, 'Payment method deleted.', null, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Settings\PaymentMethodController@destroy');
        }
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
