<?php

namespace App\Http\Controllers\v1\Customer\Auth;

use App\Enums\OtpChannelEnum;
use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Helpers\OpaqueMessageHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyResetOtpRequest;
use App\Responser\JsonResponser;
use App\Services\Auth\PasswordResetService;
use App\Support\OtpFlowLogger;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;

#[Group('Customer Auth / Forgot Password', 'OTP-based password reset for existing customers: request a reset code, verify it, then set a new password. (For setting a password from a fresh sign-up, see `POST /signup/complete` in the Registration group instead.)')]
class PasswordController extends Controller
{
    private const FLOW = 'PASSWORD_RESET';

    public function __construct(private readonly PasswordResetService $passwordResetService) {}

    /**
     * Forgot password
     *
     * Requests a password-reset OTP for the given email. To avoid account enumeration, this
     * may return 200 with a decoy `challenge_token` even when no account matches the email.
     */
    #[Endpoint('Forgot password')]
    #[Unauthenticated]
    #[BodyParam('email', 'string', 'Account email address.', required: true, example: 'joy.ene@example.com')]
    #[BodyParam('otp_channel', 'string', 'Delivery channel for the reset code.', required: false, example: 'email', enum: OtpChannelEnum::class)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Verification code sent.',
        'data' => ['challenge_token' => 'a1b2c3d4e5f6g7h8i9j0', 'expires_in' => 300],
    ], description: 'Reset code sent (or a decoy response, if opaque errors are enabled and no account matches).')]
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        OtpFlowLogger::log(self::FLOW, 'HTTP forgot-password request', OtpFlowLogger::requestMeta($request));

        try {
            $channel = OtpChannelEnum::tryFromRequest($request->input('otp_channel'));
            $payload = $this->passwordResetService->requestReset((string) $request->input('email'), $channel, $request);

            $message = OpaqueMessageHelper::authOpaqueEnabled('forgot_password')
                ? 'If an account matches what you entered, a verification code will be sent.'
                : 'Verification code sent.';

            OtpFlowLogger::log(self::FLOW, 'HTTP forgot-password response 200', OtpFlowLogger::authPayloadMeta($payload));

            return JsonResponser::send(false, $message, $payload, 200);
        } catch (\Throwable $th) {
            $response = GeneralHelper::handleControllerThrowable($th, 'Customer\Auth\PasswordController@forgotPassword');

            OtpFlowLogger::log(self::FLOW, 'HTTP forgot-password response ERROR', array_merge(
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
     * Resend forgot-password OTP
     *
     * Resends the password-reset code for an active challenge session.
     */
    #[Endpoint('Resend forgot-password OTP')]
    #[Unauthenticated]
    #[BodyParam('challenge_token', 'string', 'Challenge token from the original forgot-password request.', required: true, example: 'a1b2c3d4e5f6g7h8i9j0')]
    #[BodyParam('otp_channel', 'string', 'Delivery channel override for the resend.', required: false, example: 'email', enum: OtpChannelEnum::class)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'If the verification session is valid, a new code will be sent.',
        'data' => ['challenge_token' => 'a1b2c3d4e5f6g7h8i9j0', 'expires_in' => 300, 'cooldown_active' => false],
    ], description: 'Reset code resent.')]
    #[Response(status: 429, content: [
        'error' => true,
        'message' => 'Please wait before requesting another code.',
        'data' => ['challenge_token' => 'a1b2c3d4e5f6g7h8i9j0', 'expires_in' => 300],
    ], description: 'Resend requested too soon (cooldown active).')]
    public function forgotPasswordResend(ResendOtpRequest $request)
    {
        OtpFlowLogger::log(self::FLOW, 'HTTP forgot-password resend request', OtpFlowLogger::requestMeta($request));

        try {
            $channel = OtpChannelEnum::tryFromRequest($request->input('otp_channel'), null);
            $payload = $this->passwordResetService->resendResetOtp((string) $request->input('challenge_token'), $channel, $request);

            OtpFlowLogger::log(self::FLOW, 'HTTP forgot-password resend response 200', array_merge(
                OtpFlowLogger::authPayloadMeta($payload),
                [
                    'request_token_fp' => OtpFlowLogger::tokenFingerprint((string) $request->input('challenge_token')),
                    'token_rotated' => OtpFlowLogger::tokenFingerprint((string) $request->input('challenge_token'))
                        !== OtpFlowLogger::tokenFingerprint((string) ($payload['challenge_token'] ?? '')),
                ]
            ));

            return JsonResponser::send(false, 'If the verification session is valid, a new code will be sent.', $payload, 200);
        } catch (ApiException $e) {
            if ($e->status === 429 && $e->payload !== null) {
                OtpFlowLogger::log(self::FLOW, 'HTTP forgot-password resend response 429', array_merge(
                    OtpFlowLogger::requestMeta($request),
                    OtpFlowLogger::authPayloadMeta($e->payload),
                    ['message' => $e->getMessage()]
                ));

                return JsonResponser::send(true, $e->getMessage(), $e->payload, 429);
            }

            if (! OpaqueMessageHelper::authOpaqueEnabled('forgot_password')) {
                throw $e;
            }

            OtpFlowLogger::log(self::FLOW, 'HTTP forgot-password resend response 200 (opaque)', OtpFlowLogger::requestMeta($request));

            return JsonResponser::send(false, 'If the verification session is valid, a new code will be sent.', [
                'challenge_token' => null,
                'expires_in' => null,
            ], 200);
        } catch (\Throwable $th) {
            $response = GeneralHelper::handleControllerThrowable($th, 'Customer\Auth\PasswordController@forgotPasswordResend');

            OtpFlowLogger::log(self::FLOW, 'HTTP forgot-password resend response ERROR', array_merge(
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
     * Verify forgot-password OTP
     *
     * Confirms the password-reset code and returns a `reset_token` to use with
     * "Reset password" below.
     */
    #[Endpoint('Verify forgot-password OTP')]
    #[Unauthenticated]
    #[BodyParam('challenge_token', 'string', 'Challenge token from the forgot-password request.', required: true, example: 'a1b2c3d4e5f6g7h8i9j0')]
    #[BodyParam('otp', 'string', 'The one-time reset code.', required: true, example: '123456')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Code verified. You may now reset your password.',
        'data' => ['reset_token' => 'eyJpdiI6IkhxZnZaY01SSkV2UVdqY0F2WnBqV0E9PSIsInZhbHVlIjoi...'],
    ], description: 'OTP verified; reset token issued.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'Invalid or expired verification code.',
        'data' => null,
    ], description: 'Invalid, expired, or already-used OTP/challenge token.')]
    public function forgotPasswordVerify(VerifyResetOtpRequest $request)
    {
        OtpFlowLogger::log(self::FLOW, 'HTTP forgot-password verify request', OtpFlowLogger::requestMeta($request));

        try {
            $payload = $this->passwordResetService->verifyResetOtp(
                (string) $request->input('challenge_token'),
                (string) $request->input('otp'),
                $request
            );

            return OtpFlowLogger::logAndReturn(self::FLOW, 'HTTP forgot-password verify response 200', OtpFlowLogger::authPayloadMeta($payload),
                JsonResponser::send(false, 'Code verified. You may now reset your password.', $payload, 200));
        } catch (\Throwable $th) {
            $response = GeneralHelper::handleControllerThrowable($th, 'Customer\Auth\PasswordController@forgotPasswordVerify');

            OtpFlowLogger::log(self::FLOW, 'HTTP forgot-password verify response ERROR', array_merge(
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
     * Reset password
     *
     * Sets a new password using the `reset_token` returned by "Verify forgot-password OTP".
     * This revokes all of the account's existing tokens.
     */
    #[Endpoint('Reset password')]
    #[Unauthenticated]
    #[BodyParam('reset_token', 'string', 'The reset token from the verify-OTP step.', required: true, example: 'eyJpdiI6IkhxZnZaY01SSkV2UVdqY0F2WnBqV0E9PSIsInZhbHVlIjoi...')]
    #[BodyParam('password', 'string', 'New password: min 8 characters, mixed case, numbers, and symbols.', required: true, example: 'Str0ng!Pass1')]
    #[BodyParam('password_confirmation', 'string', 'Must match `password`.', required: true, example: 'Str0ng!Pass1')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Password reset successful.',
        'data' => null,
    ], description: 'Password updated; all existing tokens revoked.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'This password reset link has expired. Please restart the forgot password process.',
        'data' => null,
    ], description: 'Invalid or expired reset token, or new password matches the current one.')]
    public function resetPassword(ResetPasswordRequest $request)
    {
        OtpFlowLogger::log(self::FLOW, 'HTTP reset-password request', OtpFlowLogger::requestMeta($request));

        try {
            $this->passwordResetService->resetPassword(
                (string) $request->input('reset_token'),
                (string) $request->input('password'),
                $request
            );

            return OtpFlowLogger::logAndReturn(self::FLOW, 'HTTP reset-password response 200', [],
                JsonResponser::send(false, 'Password reset successful.', null, 200));
        } catch (\Throwable $th) {
            $response = GeneralHelper::handleControllerThrowable($th, 'Customer\Auth\PasswordController@resetPassword');

            OtpFlowLogger::log(self::FLOW, 'HTTP reset-password response ERROR', array_merge(
                OtpFlowLogger::requestMeta($request),
                [
                    'status' => $response->getStatusCode(),
                    'message' => $th instanceof ApiException ? $th->getMessage() : $th->getMessage(),
                ]
            ));

            return $response;
        }
    }
}
