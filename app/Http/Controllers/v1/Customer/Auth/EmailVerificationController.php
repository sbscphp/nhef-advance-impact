<?php

namespace App\Http\Controllers\v1\Customer\Auth;

use App\Enums\eClientType;
use App\Enums\OtpChannelEnum;
use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\Auth\AuthResource;
use App\Responser\JsonResponser;
use App\Services\Auth\AuthService;
use App\Support\OtpFlowLogger;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;

#[Group('Customer Auth / Email Verification (OTP)', 'Legacy OTP-code email verification. The current sign-up flow verifies email via the link sent by `POST /signup` and completed by `POST /signup/complete` (see the Registration group); these endpoints remain reachable from `POST /login` when an account still has an unverified email with a usable password.')]
class EmailVerificationController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    /**
     * Verify email via OTP
     *
     * Confirms the OTP code sent to verify a customer's email address. Returns access/refresh
     * tokens directly, or a new `challenge_token` if a login OTP is required next.
     */
    #[Endpoint('Verify email via OTP')]
    #[Unauthenticated]
    #[BodyParam('challenge_token', 'string', 'Challenge token issued when the verification OTP was sent.', required: true, example: 'a1b2c3d4e5f6g7h8i9j0')]
    #[BodyParam('otp', 'string', 'The one-time verification code.', required: true, example: '123456')]
    #[BodyParam('client', 'string', 'Requesting client type.', required: false, example: 'web', enum: eClientType::class)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Email verified.',
        'data' => [
            'access_token' => '1|xVpg1T5eD85DaIpaWHupSR9NyTzcyvmQ6A3HVY1Yc127568e',
            'refresh_token' => '2|6Wrm65vWMHQPYnbLEN6woWc6hKTzurqXjJAIYS4Ofe83a1e4',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_expires_in' => 86400,
            'registration_step' => 'completed',
            'user_type' => 'CUSTOMER',
        ],
    ], description: 'Email verified; customer signed in.')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Sign-in code sent. Please verify to complete login.',
        'data' => ['challenge_token' => 'b2c3d4e5f6g7h8i9j0k1', 'expires_in' => 300, 'registration_step' => 'completed'],
    ], description: 'Email verified; a login OTP was sent next (two-factor required).')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'Invalid or expired verification code.',
        'data' => null,
    ], description: 'Invalid, expired, or already-used OTP/challenge token.')]
    public function verify(VerifyOtpRequest $request)
    {
        $challengeToken = (string) $request->input('challenge_token');

        OtpFlowLogger::log('EMAIL_VERIFICATION', 'HTTP verify-otp request', array_merge(
            OtpFlowLogger::tokenMeta($challengeToken),
            OtpFlowLogger::otpMeta((string) $request->input('otp')),
            ['path' => $request->path()],
        ));

        try {
            $client = (string) ($request->validated('client') ?? eClientType::MOBILE->value);
            $payload = $this->authService->verifyCustomerEmailOtp(
                $challengeToken,
                (string) $request->input('otp'),
                $request,
                $client
            );

            if (isset($payload['access_token'])) {
                return OtpFlowLogger::logAndReturn('EMAIL_VERIFICATION', 'HTTP verify-otp response 200 (email verified + tokens)', [
                    'token_fp' => OtpFlowLogger::tokenFingerprint($challengeToken),
                    'has_access_token' => true,
                ], JsonResponser::send(false, 'Email verified.', AuthResource::make($payload), 200));
            }

            return OtpFlowLogger::logAndReturn('EMAIL_VERIFICATION', 'HTTP verify-otp response 200 (login OTP next)', array_merge(
                OtpFlowLogger::tokenMeta((string) ($payload['challenge_token'] ?? '')),
                ['registration_step' => $payload['registration_step'] ?? null],
            ), JsonResponser::send(false, 'Sign-in code sent. Please verify to complete login.', $payload, 200));
        } catch (\Throwable $th) {
            $response = GeneralHelper::handleControllerThrowable($th, 'Customer\Auth\EmailVerificationController@verify');

            OtpFlowLogger::log('EMAIL_VERIFICATION', 'HTTP verify-otp response ERROR', array_merge(
                OtpFlowLogger::tokenMeta($challengeToken),
                [
                    'status' => $response->getStatusCode(),
                    'message' => $th instanceof ApiException ? $th->getMessage() : $th->getMessage(),
                    'payload_keys' => $th instanceof ApiException && is_array($th->payload)
                        ? implode(',', array_keys($th->payload))
                        : null,
                ]
            ));

            return $response;
        }
    }

    /**
     * Resend email verification OTP
     *
     * Resends the email verification code for an active challenge session.
     */
    #[Endpoint('Resend email verification OTP')]
    #[Unauthenticated]
    #[BodyParam('challenge_token', 'string', 'Challenge token from the original verification OTP send.', required: true, example: 'a1b2c3d4e5f6g7h8i9j0')]
    #[BodyParam('otp_channel', 'string', 'Delivery channel override for the resend.', required: false, example: 'email', enum: OtpChannelEnum::class)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'A verification code has been sent to your email.',
        'data' => ['challenge_token' => 'a1b2c3d4e5f6g7h8i9j0', 'expires_in' => 300, 'cooldown_active' => false],
    ], description: 'Verification code resent.')]
    #[Response(status: 429, content: [
        'error' => true,
        'message' => 'Please wait before requesting another code.',
        'data' => ['challenge_token' => 'a1b2c3d4e5f6g7h8i9j0', 'expires_in' => 300],
    ], description: 'Resend requested too soon (cooldown active).')]
    public function resend(ResendOtpRequest $request)
    {
        $challengeToken = (string) $request->input('challenge_token');

        OtpFlowLogger::log('EMAIL_VERIFICATION', 'HTTP resend-otp request', array_merge(
            OtpFlowLogger::tokenMeta($challengeToken),
            ['path' => $request->path(), 'otp_channel' => $request->input('otp_channel')],
        ));

        try {
            $channel = OtpChannelEnum::tryFromRequest($request->input('otp_channel'), null);
            $payload = $this->authService->resendCustomerEmailVerificationOtp(
                $challengeToken,
                $channel,
                $request
            );

            $message = isset($payload['otp_channel'])
                ? (OtpChannelEnum::tryFrom($payload['otp_channel']) ?? OtpChannelEnum::EMAIL)->deliveryMessage()
                : 'Verification code sent.';

            OtpFlowLogger::log('EMAIL_VERIFICATION', 'HTTP resend-otp response 200', array_merge(
                OtpFlowLogger::tokenMeta((string) ($payload['challenge_token'] ?? '')),
                [
                    'request_token_fp' => OtpFlowLogger::tokenFingerprint($challengeToken),
                    'token_rotated' => OtpFlowLogger::tokenFingerprint($challengeToken)
                        !== OtpFlowLogger::tokenFingerprint((string) ($payload['challenge_token'] ?? '')),
                    'cooldown_active' => $payload['cooldown_active'] ?? null,
                    'expires_in' => $payload['expires_in'] ?? null,
                ]
            ));

            return JsonResponser::send(false, $message, $payload, 200);
        } catch (\Throwable $th) {
            $response = GeneralHelper::handleControllerThrowable($th, 'Customer\Auth\EmailVerificationController@resend');

            OtpFlowLogger::log('EMAIL_VERIFICATION', 'HTTP resend-otp response ERROR', array_merge(
                OtpFlowLogger::tokenMeta($challengeToken),
                [
                    'status' => $response->getStatusCode(),
                    'message' => $th instanceof ApiException ? $th->getMessage() : $th->getMessage(),
                ]
            ));

            return $response;
        }
    }
}
