<?php

namespace App\Http\Controllers\v1\Customer\Auth;

use App\Enums\eClientType;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CustomerRegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\Auth\AuthResource;
use App\Responser\JsonResponser;
use App\Services\Auth\AuthService;
use App\Services\DropdownService;

class RegisterController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly DropdownService $dropdownService,
    ) {}

    public function metadata()
    {
        try {
            return JsonResponser::send(
                false,
                'Registration options retrieved.',
                $this->dropdownService->customerRegistrationMetadata(),
                200
            );
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Auth\RegisterController@metadata');
        }
    }

    public function store(CustomerRegisterRequest $request)
    {
        try {
            $payload = $this->authService->registerCustomer(
                $request->validated(),
                $request
            );

            return JsonResponser::send(
                false,
                'Please check your email to verify your address and create a password.',
                $payload,
                201
            );
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Auth\RegisterController@store');
        }
    }

    public function completeRegistration(ResetPasswordRequest $request)
    {
        try {
            $client = (string) ($request->input('client') ?? eClientType::MOBILE->value);
            $payload = $this->authService->completeCustomerRegistration(
                (string) $request->input('reset_token'),
                (string) $request->input('password'),
                $request,
                $client
            );

            if (isset($payload['access_token'])) {
                return JsonResponser::send(false, 'Email verified. Welcome!', AuthResource::make($payload), 200);
            }

            return JsonResponser::send(false, 'Sign-in code sent. Please verify to complete login.', $payload, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Auth\RegisterController@completeRegistration');
        }
    }
}
