<?php

namespace App\Http\Controllers\v1\Customer\Auth;

use App\Enums\CustomerRegistrationStepEnum;
use App\Enums\eClientType;
use App\Enums\OtpChannelEnum;
use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\Auth\AuthResource;
use App\Http\Resources\Auth\TokenRefreshResource;
use App\Responser\JsonResponser;
use App\Services\Auth\AuthService;
use App\Support\OtpFlowLogger;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;

#[Group('Customer Auth / Login', 'Customer/alumni sign-in: password login (with optional two-factor OTP), token refresh, and logout.')]
class LoginController extends Controller
{
    private const FLOW = 'LOGIN';

    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Login
     *
     * Authenticates with email/password. Returns access/refresh tokens directly, or a
     * `challenge_token` if the email still needs verifying or two-factor OTP is required.
     */
    #[Endpoint('Login')]
    #[Unauthenticated]
    #[BodyParam('email', 'string', 'Customer email address.', required: true, example: 'joy.ene@example.com')]
    #[BodyParam('password', 'string', 'Account password.', required: true, example: 'Str0ng!Pass1')]
    #[BodyParam('client', 'string', 'Requesting client type.', required: false, example: 'web', enum: eClientType::class)]
    #[BodyParam('otp_channel', 'string', 'Preferred channel if an OTP needs to be sent.', required: false, example: 'email', enum: OtpChannelEnum::class)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Login successful.',
        'data' => [
            'access_token' => '1|xVpg1T5eD85DaIpaWHupSR9NyTzcyvmQ6A3HVY1Yc127568e',
            'refresh_token' => '2|6Wrm65vWMHQPYnbLEN6woWc6hKTzurqXjJAIYS4Ofe83a1e4',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_expires_in' => 86400,
            'registration_step' => 'completed',
            'user_type' => 'CUSTOMER',
        ],
    ], description: 'Signed in directly (no two-factor required).')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Verification code sent.',
        'data' => ['challenge_token' => 'a1b2c3d4e5f6g7h8i9j0', 'expires_in' => 300, '2fa' => true, 'registration_step' => 'completed'],
    ], description: 'Two-factor login OTP sent; call verify-otp next.')]
    #[Response(status: 400, content: [
        'error' => true,
        'message' => 'Invalid credentials.',
        'data' => null,
    ], description: 'Invalid email/password, locked, or inactive account.')]
    public function login(LoginRequest $request)
    {
        OtpFlowLogger::log(self::FLOW, 'HTTP login request', OtpFlowLogger::requestMeta($request));

        try {
            $client = (string) ($request->validated('client') ?? eClientType::MOBILE->value);
            $payload = $this->authService->loginCustomer(
                (string) $request->input('email'),
                (string) $request->input('password'),
                $request,
                $client
            );

            if (isset($payload['access_token'])) {
                return OtpFlowLogger::logAndReturn(self::FLOW, 'HTTP login response 200 (direct login)', OtpFlowLogger::authPayloadMeta($payload),
                    JsonResponser::send(false, 'Login successful.', AuthResource::make($payload), 200));
            }

            $message = ($payload['registration_step'] ?? '') === CustomerRegistrationStepEnum::AWAITING_OTP->value
                ? 'Please verify your email. A verification code has been sent.'
                : 'Verification code sent.';

            OtpFlowLogger::log(self::FLOW, 'HTTP login response 200 (OTP required)', OtpFlowLogger::authPayloadMeta($payload));

            return JsonResponser::send(false, $message, $payload, 200);
        } catch (\Throwable $th) {
            $response = GeneralHelper::handleControllerThrowable($th, 'Customer\Auth\LoginController@login');

            OtpFlowLogger::log(self::FLOW, 'HTTP login response ERROR', array_merge(
                OtpFlowLogger::requestMeta($request),
                [
                    'status' => $response->getStatusCode(),
                    'message' => $th instanceof ApiException ? $th->getMessage() : $th->getMessage(),
                ]
            ));

            return $response;
        }
    }

    /**
     * Verify login OTP
     *
     * Confirms the two-factor OTP sent during login and returns access/refresh tokens.
     */
    #[Endpoint('Verify login OTP')]
    #[Unauthenticated]
    #[BodyParam('challenge_token', 'string', 'Challenge token issued by the login endpoint.', required: true, example: 'a1b2c3d4e5f6g7h8i9j0')]
    #[BodyParam('otp', 'string', 'The one-time login code.', required: true, example: '123456')]
    #[BodyParam('client', 'string', 'Requesting client type.', required: false, example: 'web', enum: eClientType::class)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Login successful.',
        'data' => [
            'access_token' => '1|xVpg1T5eD85DaIpaWHupSR9NyTzcyvmQ6A3HVY1Yc127568e',
            'refresh_token' => '2|6Wrm65vWMHQPYnbLEN6woWc6hKTzurqXjJAIYS4Ofe83a1e4',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_expires_in' => 86400,
            'registration_step' => 'completed',
            'user_type' => 'CUSTOMER',
        ],
    ], description: 'OTP verified; customer signed in.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'Invalid or expired verification code.',
        'data' => null,
    ], description: 'Invalid, expired, or already-used OTP/challenge token.')]
    public function verifyOtp(VerifyOtpRequest $request)
    {
        OtpFlowLogger::log(self::FLOW, 'HTTP verify-otp request', OtpFlowLogger::requestMeta($request));

        try {
            $client = (string) ($request->validated('client') ?? eClientType::MOBILE->value);
            $payload = $this->authService->verifyCustomerLoginOtp(
                (string) $request->input('challenge_token'),
                (string) $request->input('otp'),
                $request,
                $client
            );

            return OtpFlowLogger::logAndReturn(self::FLOW, 'HTTP verify-otp response 200', OtpFlowLogger::authPayloadMeta($payload),
                JsonResponser::send(false, 'Login successful.', AuthResource::make($payload), 200));
        } catch (\Throwable $th) {
            $response = GeneralHelper::handleControllerThrowable($th, 'Customer\Auth\LoginController@verifyOtp');

            OtpFlowLogger::log(self::FLOW, 'HTTP verify-otp response ERROR', array_merge(
                OtpFlowLogger::requestMeta($request),
                [
                    'status' => $response->getStatusCode(),
                    'message' => $th instanceof ApiException ? $th->getMessage() : $th->getMessage(),
                ]
            ));

            return $response;
        }
    }

    /**
     * Resend login OTP
     *
     * Resends the two-factor login code for an active challenge session.
     */
    #[Endpoint('Resend login OTP')]
    #[Unauthenticated]
    #[BodyParam('challenge_token', 'string', 'Challenge token from the original login OTP send.', required: true, example: 'a1b2c3d4e5f6g7h8i9j0')]
    #[BodyParam('otp_channel', 'string', 'Delivery channel override for the resend.', required: false, example: 'email', enum: OtpChannelEnum::class)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'A verification code has been sent to your email.',
        'data' => ['challenge_token' => 'a1b2c3d4e5f6g7h8i9j0', 'expires_in' => 300, 'cooldown_active' => false],
    ], description: 'Login OTP resent.')]
    #[Response(status: 429, content: [
        'error' => true,
        'message' => 'Please wait before requesting another code.',
        'data' => ['challenge_token' => 'a1b2c3d4e5f6g7h8i9j0', 'expires_in' => 300],
    ], description: 'Resend requested too soon (cooldown active).')]
    public function resendOtp(ResendOtpRequest $request)
    {
        OtpFlowLogger::log(self::FLOW, 'HTTP resend-otp request', OtpFlowLogger::requestMeta($request));

        try {
            $channel = OtpChannelEnum::tryFromRequest($request->input('otp_channel'), null);
            $payload = $this->authService->resendCustomerLoginOtp(
                (string) $request->input('challenge_token'),
                $channel,
                $request
            );

            $message = isset($payload['otp_channel'])
                ? (OtpChannelEnum::tryFrom($payload['otp_channel']) ?? OtpChannelEnum::EMAIL)->deliveryMessage()
                : 'Verification code sent.';

            OtpFlowLogger::log(self::FLOW, 'HTTP resend-otp response 200', array_merge(
                OtpFlowLogger::authPayloadMeta($payload),
                [
                    'request_token_fp' => OtpFlowLogger::tokenFingerprint((string) $request->input('challenge_token')),
                    'token_rotated' => OtpFlowLogger::tokenFingerprint((string) $request->input('challenge_token'))
                        !== OtpFlowLogger::tokenFingerprint((string) ($payload['challenge_token'] ?? '')),
                ]
            ));

            return JsonResponser::send(false, $message, $payload, 200);
        } catch (\Throwable $th) {
            $response = GeneralHelper::handleControllerThrowable($th, 'Customer\Auth\LoginController@resendOtp');

            OtpFlowLogger::log(self::FLOW, 'HTTP resend-otp response ERROR', array_merge(
                OtpFlowLogger::requestMeta($request),
                [
                    'status' => $response->getStatusCode(),
                    'message' => $th instanceof ApiException ? $th->getMessage() : $th->getMessage(),
                ]
            ));

            return $response;
        }
    }

    /**
     * Refresh token
     *
     * Exchanges a valid refresh token for a new access token (and refresh token, if rotation
     * is enabled).
     */
    #[Endpoint('Refresh token')]
    #[Unauthenticated]
    #[BodyParam('refresh_token', 'string', 'A valid, unexpired refresh token.', required: true, example: '2|6Wrm65vWMHQPYnbLEN6woWc6hKTzurqXjJAIYS4Ofe83a1e4')]
    #[BodyParam('client', 'string', 'Requesting client type.', required: false, example: 'web', enum: eClientType::class)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Token refreshed successfully.',
        'data' => [
            'access_token' => '3|newAccessTokenExample',
            'refresh_token' => '4|newRefreshTokenExample',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_expires_in' => 86400,
        ],
    ], description: 'New tokens issued.')]
    #[Response(status: 401, content: [
        'error' => true,
        'message' => 'Invalid or expired refresh token.',
        'data' => null,
    ], description: 'Invalid, expired, or revoked refresh token.')]
    public function refresh(RefreshTokenRequest $request)
    {
        try {
            $client = (string) ($request->validated('client') ?? eClientType::MOBILE->value);
            $payload = $this->authService->refreshCustomerToken(
                (string) $request->input('refresh_token'),
                $request,
                $client
            );

            return JsonResponser::send(false, 'Token refreshed successfully.', TokenRefreshResource::make($payload), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Auth\LoginController@refresh');
        }
    }

    /**
     * Logout
     *
     * Revokes the current access/refresh token pair for the authenticated customer.
     */
    #[Endpoint('Logout')]
    #[Authenticated]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Logged out successfully!',
        'data' => null,
    ], description: 'Session revoked.')]
    public function logout(Request $request)
    {
        try {
            $this->authService->logout($request->user());

            return JsonResponser::send(false, 'Logged out successfully!', null, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Auth\LoginController@logout');
        }
    }
}
