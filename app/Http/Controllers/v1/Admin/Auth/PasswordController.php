<?php

namespace App\Http\Controllers\v1\Admin\Auth;

use App\Enums\OtpChannelEnum;
use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Helpers\OpaqueMessageHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordLinkRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\ResetTokenRequest;
use App\Http\Requests\Auth\VerifyResetOtpRequest;
use App\Responser\JsonResponser;
use App\Services\Auth\PasswordResetService;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;

#[Group('Admin Auth / Forgot Password', 'Two interchangeable password-reset flows for existing admins, picked by which endpoint the frontend calls first: (1) OTP-code flow, request a reset code, verify it, then set a new password; or (2) one-click email-link flow, request a link, then go straight to `reset-password/context` + `reset-password`. Both end at the same `reset-password` call and use the same kind of reset token, which is also what an admin invite email carries.')]
class PasswordController extends Controller
{
    public function __construct(private readonly PasswordResetService $passwordResetService) {}

    #[Endpoint('Forgot password')]
    #[Unauthenticated]
    #[BodyParam('email', 'string', 'Admin account email address.', required: true, example: 'jane.doe@nhef.org')]
    #[BodyParam('otp_channel', 'string', 'Delivery channel for the reset code.', required: false, example: 'email', enum: OtpChannelEnum::class)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Verification code sent.',
        'data' => ['challenge_token' => 'a1b2c3d4e5f6g7h8i9j0', 'expires_in' => 300],
    ], description: 'Reset code sent (or a decoy response, if opaque errors are enabled and no account matches).')]
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        try {
            $email = strtolower(trim((string) $request->input('email')));
            $payload = $this->passwordResetService->requestAdminReset($email, $request);

            $message = OpaqueMessageHelper::authOpaqueEnabled('forgot_password')
                ? 'If an account matches what you entered, a verification code will be sent.'
                : 'Verification code sent.';

            return JsonResponser::send(false, $message, $payload, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Auth\PasswordController@forgotPassword');
        }
    }

    #[Endpoint('Forgot password (email link)', 'Alternative to the OTP-code flow above: sends a one-click password reset link by email instead of a code. Sent synchronously (not queued), so it does not depend on a queue worker running. The invitee lands on the same create-new-password screen used for admin invites; call `reset-password/context` then `reset-password` exactly as you would for an invite token.')]
    #[Unauthenticated]
    #[BodyParam('email', 'string', 'Admin account email address.', required: true, example: 'jane.doe@nhef.org')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'If an account matches what you entered, a password reset link will be sent.',
        'data' => null,
    ], description: 'Reset link sent (or a decoy response, if opaque errors are enabled and no account matches).')]
    public function forgotPasswordLink(ForgotPasswordLinkRequest $request)
    {
        try {
            $email = strtolower(trim((string) $request->input('email')));
            $this->passwordResetService->requestAdminResetLink($email, $request);

            $message = OpaqueMessageHelper::authOpaqueEnabled('forgot_password')
                ? 'If an account matches what you entered, a password reset link will be sent.'
                : 'Password reset link sent.';

            return JsonResponser::send(false, $message, null, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Auth\PasswordController@forgotPasswordLink');
        }
    }

    #[Endpoint('Resend forgot-password OTP')]
    #[Unauthenticated]
    #[BodyParam('challenge_token', 'string', 'Challenge token from the original forgot-password request.', required: true, example: 'a1b2c3d4e5f6g7h8i9j0')]
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
        try {
            $payload = $this->passwordResetService->resendAdminResetOtp((string) $request->input('challenge_token'), $request);

            return JsonResponser::send(false, 'If the verification session is valid, a new code will be sent.', $payload, 200);
        } catch (ApiException $e) {
            if ($e->status === 429 && $e->payload !== null) {
                return JsonResponser::send(true, $e->getMessage(), $e->payload, 429);
            }

            if (! OpaqueMessageHelper::authOpaqueEnabled('forgot_password')) {
                throw $e;
            }

            return JsonResponser::send(false, 'If the verification session is valid, a new code will be sent.', [
                'challenge_token' => null,
                'expires_in' => null,
            ], 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Auth\PasswordController@forgotPasswordResend');
        }
    }

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
        try {
            $payload = $this->passwordResetService->verifyAdminResetOtp(
                (string) $request->input('challenge_token'),
                (string) $request->input('otp'),
                $request
            );

            return JsonResponser::send(false, 'Code verified. You may now reset your password.', $payload, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Auth\PasswordController@forgotPasswordVerify');
        }
    }

    #[Endpoint('Resolve reset/invite token', "Resolves a reset or account-setup token to the account it belongs to, so the frontend can render \"Hi {email}\" and the correct copy (invite vs. forgot-password) before the password form is submitted. The token itself is Laravel-encrypted and can't be read client-side.")]
    #[Unauthenticated]
    #[BodyParam('token', 'string', 'The `token` query parameter from the forgot-password or invite email link.', required: true, example: 'eyJpdiI6IkhxZnZaY01SSkV2UVdqY0F2WnBqV0E9PSIsInZhbHVlIjoi...')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Reset token verified.',
        'data' => ['email' => 'jane.doe@nhef.org', 'name' => 'Jane Doe', 'is_invite' => true],
    ], description: 'Token is valid; account context returned.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'This account setup link has expired. Please contact your administrator to resend the invitation, or use Forgot Password on the login page.',
        'data' => null,
    ], description: 'Invalid or expired token.')]
    public function resetPasswordContext(ResetTokenRequest $request)
    {
        try {
            $payload = $this->passwordResetService->resetPasswordContext((string) $request->input('token'));

            return JsonResponser::send(false, 'Reset token verified.', $payload, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Auth\PasswordController@resetPasswordContext');
        }
    }

    #[Endpoint('Reset password')]
    #[Unauthenticated]
    #[BodyParam('reset_token', 'string', 'The reset token from the verify-OTP step, or the `token` query parameter from an invite email.', required: true, example: 'eyJpdiI6IkhxZnZaY01SSkV2UVdqY0F2WnBqV0E9PSIsInZhbHVlIjoi...')]
    #[BodyParam('password', 'string', 'New password: min 8 characters, mixed case, numbers, and symbols.', required: true, example: 'Str0ng!Pass1')]
    #[BodyParam('password_confirmation', 'string', 'Must match `password`.', required: true, example: 'Str0ng!Pass1')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Password reset successful. You may now log in.',
        'data' => null,
    ], description: 'Password updated; all existing tokens revoked.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'This password reset link has expired. Please restart the forgot password process.',
        'data' => null,
    ], description: 'Invalid or expired reset token, or new password matches the current one.')]
    public function resetPassword(ResetPasswordRequest $request)
    {
        try {
            $this->passwordResetService->resetPassword(
                (string) $request->input('reset_token'),
                (string) $request->input('password'),
                $request
            );

            return JsonResponser::send(false, 'Password reset successful. You may now log in.', null, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Auth\PasswordController@resetPassword');
        }
    }
}
