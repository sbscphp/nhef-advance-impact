<?php

namespace App\Http\Controllers\v1\Customer\Auth;

use App\Enums\DegreeEnum;
use App\Enums\eClientType;
use App\Enums\EmploymentStatusEnum;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CustomerRegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\Auth\AuthResource;
use App\Responser\JsonResponser;
use App\Services\Auth\AuthService;
use App\Services\DropdownService;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;

#[Group('Customer Auth / Registration', 'Alumni/customer sign-up: fetch form dropdown options, submit sign-up (no password collected), then complete registration by setting a password from the emailed "Verify Email Address" link.')]
class RegisterController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly DropdownService $dropdownService,
    ) {}

    /**
     * Get registration options
     *
     * Dropdown metadata for the sign-up form: countries, degrees, employment statuses,
     * and the list of selectable graduation years.
     */
    #[Endpoint('Get registration options')]
    #[Unauthenticated]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Registration options retrieved.',
        'data' => [
            'countries' => [
                ['uuid' => 'c11a0fab-5699-44aa-99d5-11861324f033', 'name' => 'Nigeria', 'iso2' => 'NG', 'dial_code' => '+234', 'flag_emoji' => null],
            ],
            'otp_channels' => [
                ['value' => 'email', 'label' => 'Email'],
                ['value' => 'sms', 'label' => 'SMS'],
            ],
            'degrees' => [
                ['value' => 'BSc', 'label' => 'BSc'],
                ['value' => 'BEd', 'label' => 'BEd'],
                ['value' => 'BA', 'label' => 'BA'],
                ['value' => 'BEng', 'label' => 'BEng'],
                ['value' => 'LLB', 'label' => 'LLB'],
                ['value' => 'BTech', 'label' => 'BTech'],
                ['value' => 'MSc', 'label' => 'MSc'],
                ['value' => 'MA', 'label' => 'MA'],
                ['value' => 'MBA', 'label' => 'MBA'],
                ['value' => 'MEng', 'label' => 'MEng'],
                ['value' => 'MPH', 'label' => 'MPH'],
            ],
            'employment_statuses' => [
                ['value' => 'Employed', 'label' => 'Employed'],
                ['value' => 'Unemployed', 'label' => 'Unemployed'],
                ['value' => 'Self Employed', 'label' => 'Self Employed'],
                ['value' => 'Retired', 'label' => 'Retired'],
            ],
            'graduation_years' => [2026, 2025, 2024, 2023, 2022],
            'default_country_uuid' => 'c11a0fab-5699-44aa-99d5-11861324f033',
            'default_dial_code' => '+234',
        ],
    ], description: 'Registration options retrieved.')]
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

    /**
     * Sign up
     *
     * Creates a new alumni/customer account and emails a "Verify Email Address" link.
     * No password is collected at this step; the customer sets one after clicking the link
     * (see "Complete registration" below).
     */
    #[Endpoint('Sign up')]
    #[Unauthenticated]
    #[BodyParam('firstname', 'string', 'Customer first name.', required: true, example: 'Joy')]
    #[BodyParam('lastname', 'string', 'Customer last name.', required: true, example: 'Ene')]
    #[BodyParam('email', 'string', 'Customer email address. Must be unique.', required: true, example: 'joy.ene@example.com')]
    #[BodyParam('phone_number', 'string', 'E.164-formatted phone number.', required: true, example: '+2348012345678')]
    #[BodyParam('country_uuid', 'string', 'UUID of an active country (see registration options). Defaults to the platform default country.', required: false, example: 'c11a0fab-5699-44aa-99d5-11861324f033')]
    #[BodyParam('country_code', 'string', 'Dial code override, e.g. +234.', required: false, example: '+234')]
    #[BodyParam('client', 'string', 'Requesting client type.', required: false, example: 'web', enum: eClientType::class)]
    #[BodyParam('matric_no', 'string', 'Matriculation number.', required: false, example: '1234567')]
    #[BodyParam('department', 'string', 'Academic department.', required: true, example: 'Accounting')]
    #[BodyParam('year_of_graduation', 'integer', 'Graduation year (see registration options for the selectable range).', required: true, example: 2024)]
    #[BodyParam('degree_earned', 'string', 'Degree earned.', required: true, example: 'BSc', enum: DegreeEnum::class)]
    #[BodyParam('employment_status', 'string', 'Current employment status.', required: true, example: 'Employed', enum: EmploymentStatusEnum::class)]
    #[BodyParam('organisation_name', 'string', 'Current employer / organisation name.', required: false, example: 'Acme Ltd')]
    #[BodyParam('position', 'string', 'Job title / position.', required: false, example: 'Analyst')]
    #[Response(status: 201, content: [
        'error' => false,
        'message' => 'Please check your email to verify your address and create a password.',
        'data' => ['registration_step' => 'awaiting_email_verification'],
    ], description: 'Sign-up successful; verification email sent.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'An account with this email already exists. Would you like to log in instead, or use a different email?',
        'data' => null,
    ], description: 'Validation error.')]
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

    /**
     * Complete registration
     *
     * Called from the "Verify Email Address" link (`reset_token` is the `token` query
     * parameter on that link). Sets the customer's password, marks their email verified,
     * and signs them in (or issues a login OTP if two-factor is required).
     */
    #[Endpoint('Complete registration (set password)')]
    #[Unauthenticated]
    #[BodyParam('reset_token', 'string', 'The `token` query parameter from the "Verify Email Address" email link.', required: true, example: 'eyJpdiI6IkhxZnZaY01SSkV2UVdqY0F2WnBqV0E9PSIsInZhbHVlIjoi...')]
    #[BodyParam('password', 'string', 'New password: min 8 characters, mixed case, numbers, and symbols.', required: true, example: 'Str0ng!Pass1')]
    #[BodyParam('password_confirmation', 'string', 'Must match `password`.', required: true, example: 'Str0ng!Pass1')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Email verified. Welcome!',
        'data' => [
            'access_token' => '1|xVpg1T5eD85DaIpaWHupSR9NyTzcyvmQ6A3HVY1Yc127568e',
            'refresh_token' => '2|6Wrm65vWMHQPYnbLEN6woWc6hKTzurqXjJAIYS4Ofe83a1e4',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_expires_in' => 86400,
            'registration_step' => 'completed',
            'user_type' => 'CUSTOMER',
            'user' => ['uuid' => '3cec419a-27be-43b3-9af2-7a8949fca566', 'firstname' => 'Joy', 'lastname' => 'Ene', 'email' => 'joy.ene@example.com'],
        ],
    ], description: 'Password set, email verified, and customer signed in.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'This password reset link has expired. Please restart the forgot password process.',
        'data' => null,
    ], description: 'Invalid or expired verification link.')]
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
