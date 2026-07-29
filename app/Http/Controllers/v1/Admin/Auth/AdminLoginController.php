<?php

namespace App\Http\Controllers\v1\Admin\Auth;

use App\Enums\eClientType;
use App\Enums\OtpChannelEnum;
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
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;

#[Group('Admin Auth / Login', "Admin sign-in: password login (with optional two-factor OTP, toggled per admin under Settings), token refresh, and logout. A first-time or invited admin whose password hasn't been set yet gets a 403 with `must_reset_password: true` instead of a token; send them through the forgot-password/reset-password flow.")]
class AdminLoginController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    #[Endpoint('Login')]
    #[Unauthenticated]
    #[BodyParam('email', 'string', 'Admin account email address.', required: true, example: 'jane.doe@nhef.org')]
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
            'user_type' => 'ADMIN',
            'user' => [
                'uuid' => 'a1b2c3d4-e5f6-4789-a0b1-c2d3e4f5a6b7',
                'name' => 'Jane Doe',
                'email' => 'jane.doe@nhef.org',
                'must_reset_password' => false,
                'roles' => ['Fundraising Officer'],
                'permissions' => ['campaigns.read', 'campaigns.create'],
            ],
        ],
    ], description: 'Signed in directly (no two-factor required).')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Verification code sent.',
        'data' => ['challenge_token' => 'a1b2c3d4e5f6g7h8i9j0', 'expires_in' => 300],
    ], description: 'Two-factor login OTP sent; call login/verify-otp next.')]
    #[Response(status: 400, content: [
        'error' => true,
        'message' => 'Invalid credentials.',
        'data' => null,
    ], description: 'Invalid email/password, locked, or inactive account.')]
    #[Response(status: 403, content: [
        'error' => true,
        'message' => 'Password reset is required before you can continue.',
        'data' => ['must_reset_password' => true],
    ], description: "Invited admin hasn't set a password yet, or an admin was force-reset; direct them to Forgot Password / their invite email link.")]
    public function login(LoginRequest $request)
    {
        try {
            $payload = $this->authService->loginAdmin(
                (string) $request->input('email'),
                (string) $request->input('password'),
                $request,
                eClientType::WEB->value
            );

            if (isset($payload['access_token'])) {
                return JsonResponser::send(false, 'Login successful.', AuthResource::make($payload), 200);
            }

            return JsonResponser::send(false, 'Verification code sent.', $payload, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'AdminLoginController@login');
        }
    }

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
            'user_type' => 'ADMIN',
        ],
    ], description: 'OTP verified; admin signed in.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'Invalid or expired verification code.',
        'data' => null,
    ], description: 'Invalid, expired, or already-used OTP/challenge token.')]
    public function verifyOtp(VerifyOtpRequest $request)
    {
        try {
            $payload = $this->authService->verifyAdminLoginOtp(
                (string) $request->input('challenge_token'),
                (string) $request->input('otp'),
                $request,
                eClientType::WEB->value
            );

            return JsonResponser::send(false, 'Login successful.', AuthResource::make($payload), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'AdminLoginController@verifyOtp');
        }
    }

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
        try {
            $channel = OtpChannelEnum::tryFromRequest($request->input('otp_channel'), null);
            $payload = $this->authService->resendAdminLoginOtp(
                (string) $request->input('challenge_token'),
                $channel,
                $request
            );

            $message = isset($payload['otp_channel'])
                ? (OtpChannelEnum::tryFrom($payload['otp_channel']) ?? OtpChannelEnum::EMAIL)->deliveryMessage()
                : 'Verification code sent.';

            return JsonResponser::send(false, $message, $payload, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'AdminLoginController@resendOtp');
        }
    }

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
            $payload = $this->authService->refreshAdminToken(
                (string) $request->input('refresh_token'),
                $request,
                eClientType::WEB->value
            );

            return JsonResponser::send(false, 'Token refreshed successfully.', TokenRefreshResource::make($payload), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'AdminLoginController@refresh');
        }
    }

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
            return GeneralHelper::handleControllerThrowable($th, 'AdminLoginController@logout');
        }
    }
}
