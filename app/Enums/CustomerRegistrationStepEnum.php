<?php

namespace App\Enums;

/**
 * Donor registration flow only: account created → confirm email → done.
 * Login two-factor OTP is separate and does not change this step (it stays completed once email is verified).
 */
enum CustomerRegistrationStepEnum: string
{
    /** User registered (or must verify email on login); waiting for email OTP confirmation. */
    case AWAITING_OTP = 'awaiting_otp';

    /** Sign-up submitted; waiting for the "Verify Email Address" link to be clicked and a password set. */
    case AWAITING_EMAIL_VERIFICATION = 'awaiting_email_verification';

    /** Email OTP confirmed, or email verification link completed; registration is complete. */
    case COMPLETED = 'completed';
}
