<?php

namespace App\Http\Controllers\v1\Customer\Settings;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Settings\PaymentMethodResource;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Settings\PaymentMethodService;
use Illuminate\Http\Request;

/**
 * Saved cards, populated automatically whenever the gateway returns a reusable authorization
 * for a logged-in customer's payment; there is no "add a card" endpoint, only this side effect.
 */
class PaymentMethodController extends Controller
{
    public function __construct(private readonly PaymentMethodService $paymentMethodService) {}

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
