<?php

use App\Http\Controllers\v1\Customer\Auth\EmailVerificationController;
use App\Http\Controllers\v1\Customer\Auth\LoginController;
use App\Http\Controllers\v1\Customer\Auth\PasswordController;
use App\Http\Controllers\v1\Customer\Auth\RegisterController;
use App\Http\Controllers\v1\Customer\Events\EventRegistrationController;
use App\Http\Controllers\v1\Customer\Fundraising\DonationController;
use App\Http\Controllers\v1\Customer\Fundraising\PledgeController;
use App\Http\Controllers\v1\Customer\Mentorship\MenteeController;
use App\Http\Controllers\v1\Customer\Mentorship\MentorController;
use App\Http\Controllers\v1\Customer\Mentorship\MentorshipReviewController;
use App\Http\Controllers\v1\Customer\Networking\AlumniSearchController as NetworkingAlumniSearchController;
use App\Http\Controllers\v1\Customer\Networking\ChannelController as NetworkingChannelController;
use App\Http\Controllers\v1\Customer\Networking\MessageController as NetworkingMessageController;
use App\Http\Controllers\v1\Customer\Networking\ReactionController as NetworkingReactionController;
use App\Http\Controllers\v1\Customer\Notification\NotificationController;
use App\Http\Controllers\v1\Customer\Recognition\RecognitionController;
use App\Http\Controllers\v1\Customer\Settings\PaymentMethodController;
use App\Http\Controllers\v1\Customer\Settings\SettingsController;
use App\Http\Controllers\v1\Customer\TertiaryInstitutionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::get('registration/options', [RegisterController::class, 'metadata'])->middleware('throttle:60,1');
        Route::post('signup', [RegisterController::class, 'store'])->middleware('throttle:customer-register');
        Route::post('signup/complete', [RegisterController::class, 'completeRegistration'])->middleware('throttle:customer-otp-verify');
        Route::post('email/verify-otp', [EmailVerificationController::class, 'verify'])->middleware('throttle:customer-otp-verify');
        Route::post('email/resend-otp', [EmailVerificationController::class, 'resend'])->middleware('throttle:customer-otp-send');

        Route::post('login', [LoginController::class, 'login'])->middleware('throttle:customer-login');
        Route::post('login/verify-otp', [LoginController::class, 'verifyOtp'])->middleware('throttle:customer-otp-verify');
        Route::post('login/resend-otp', [LoginController::class, 'resendOtp'])->middleware('throttle:customer-otp-send');

        Route::post('forgot-password', [PasswordController::class, 'forgotPassword'])->middleware('throttle:customer-otp-send');
        Route::post('forgot-password/resend', [PasswordController::class, 'forgotPasswordResend'])->middleware('throttle:customer-otp-send');
        Route::post('forgot-password/verify', [PasswordController::class, 'forgotPasswordVerify'])->middleware('throttle:customer-otp-verify');
        Route::post('reset-password', [PasswordController::class, 'resetPassword'])->middleware('throttle:customer-otp-verify');
        Route::post('refresh', [LoginController::class, 'refresh'])->middleware('throttle:customer-token-refresh');

        Route::middleware('auth:sanctum')->post('logout', [LoginController::class, 'logout']);
    });

    // No auth: needed on the signup form (before an account exists), not just Settings.
    Route::get('tertiary-institutions', [TertiaryInstitutionController::class, 'index'])->middleware('throttle:60,1');

    Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('/read-all', [NotificationController::class, 'markAllRead']);
        Route::get('/{id}', [NotificationController::class, 'show']);
        Route::patch('/{id}/read', [NotificationController::class, 'markRead']);
        Route::patch('/{id}/unread', [NotificationController::class, 'markUnread']);
        Route::delete('/{id}/dismiss', [NotificationController::class, 'dismiss']);
    });

    Route::middleware('auth:sanctum')->prefix('settings')->group(function () {
        Route::get('/profile', [SettingsController::class, 'profile']);
        Route::match(['patch', 'post'], '/profile', [SettingsController::class, 'updateProfile']);
        Route::match(['patch', 'post'], '/2fa', [SettingsController::class, 'toggleTwoFactor']);
        Route::match(['patch', 'post'], '/biometrics', [SettingsController::class, 'toggleBiometrics']);
        Route::match(['patch', 'post'], '/password', [SettingsController::class, 'changePassword']);
        Route::match(['patch', 'post'], '/notifications', [SettingsController::class, 'updateNotificationPreferences']);

        Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
        Route::patch('/payment-methods/{uuid}/default', [PaymentMethodController::class, 'setDefault']);
        Route::delete('/payment-methods/{uuid}', [PaymentMethodController::class, 'destroy']);
    });

    Route::middleware('auth:sanctum')->prefix('pledges')->group(function () {
        Route::get('/', [PledgeController::class, 'index']);
        Route::post('/', [PledgeController::class, 'store'])->middleware('throttle:customer-pledge-create');
        Route::get('/{uuid}', [PledgeController::class, 'show']);
        Route::post('/{uuid}/installments/{installmentUuid}/pay', [PledgeController::class, 'payInstallment']);
    });

    Route::middleware('auth:sanctum')->prefix('donations')->group(function () {
        Route::get('/', [DonationController::class, 'index']);
        Route::post('/', [DonationController::class, 'store'])->middleware('throttle:customer-donation-create');
        // Must be registered before /{uuid}; otherwise "payments" would be swallowed as a
        // donation uuid by the wildcard route below.
        Route::get('/payments', [DonationController::class, 'paymentHistory']);
        Route::get('/payments/overview', [DonationController::class, 'paymentHistoryOverview']);
        Route::get('/payments/{uuid}', [DonationController::class, 'showPayment']);
        Route::get('/{uuid}', [DonationController::class, 'show']);
        Route::post('/{uuid}/charge', [DonationController::class, 'chargeNext']);
        Route::patch('/{uuid}', [DonationController::class, 'modify']);
        Route::post('/{uuid}/pause', [DonationController::class, 'pause']);
        Route::post('/{uuid}/resume', [DonationController::class, 'resume']);
        Route::post('/{uuid}/cancel', [DonationController::class, 'cancel']);
    });

    Route::middleware('auth:sanctum')->prefix('recognition')->group(function () {
        Route::get('/me', [RecognitionController::class, 'me']);
    });

    Route::middleware('auth:sanctum')->prefix('events')->group(function () {
        // Must be registered before /{uuid}/register; otherwise "registrations" would be
        // swallowed as an event uuid by the wildcard route below.
        Route::get('/registrations', [EventRegistrationController::class, 'index']);
        Route::get('/registrations/{uuid}', [EventRegistrationController::class, 'show']);
        Route::post('/{uuid}/register', [EventRegistrationController::class, 'store'])->middleware('throttle:customer-event-register');
    });

    Route::middleware('auth:sanctum')->prefix('mentorship')->group(function () {
        // Must be registered before /mentors/{uuid}; otherwise "me", "pause", and "resume" would
        // be swallowed as a mentor uuid by the wildcard route below.
        Route::post('/mentors/apply', [MentorController::class, 'apply'])->middleware('throttle:customer-mentorship-apply');
        Route::get('/mentors/me', [MentorController::class, 'me']);
        Route::post('/mentors/pause', [MentorController::class, 'pause']);
        Route::post('/mentors/resume', [MentorController::class, 'resume']);
        Route::get('/mentors', [MentorController::class, 'index']);
        Route::get('/mentors/{uuid}', [MentorController::class, 'show']);
        Route::post('/mentors/{uuid}/reviews', [MentorshipReviewController::class, 'store']);
        Route::get('/mentors/{uuid}/reviews', [MentorshipReviewController::class, 'index']);

        // Same wildcard-ordering reason: /mentees/apply and /mentees/me before /mentees/{uuid}.
        Route::post('/mentees/apply', [MenteeController::class, 'apply'])->middleware('throttle:customer-mentorship-apply');
        Route::get('/mentees/me', [MenteeController::class, 'me']);
        Route::get('/mentees', [MenteeController::class, 'index']);
        Route::get('/mentees/{uuid}', [MenteeController::class, 'show']);
    });

    Route::middleware('auth:sanctum')->prefix('networking')->group(function () {
        Route::get('/conversations', [NetworkingChannelController::class, 'index']);
        Route::get('/alumni/search', [NetworkingAlumniSearchController::class, 'index']);
        Route::post('/direct-messages', [NetworkingChannelController::class, 'startDirect']);

        // Must be registered before /channels/{uuid}; otherwise "browse" would be swallowed
        // as a channel uuid by the wildcard route below.
        Route::get('/channels/browse', [NetworkingChannelController::class, 'browse']);
        Route::get('/channels/{uuid}', [NetworkingChannelController::class, 'show']);
        Route::post('/channels/{uuid}/join', [NetworkingChannelController::class, 'join']);
        Route::post('/channels/{uuid}/leave', [NetworkingChannelController::class, 'leave']);
        Route::get('/channels/{uuid}/members', [NetworkingChannelController::class, 'members']);

        Route::get('/channels/{uuid}/messages', [NetworkingMessageController::class, 'index']);
        Route::post('/channels/{uuid}/messages', [NetworkingMessageController::class, 'store'])->middleware('throttle:customer-networking-message-send');
        Route::post('/channels/{uuid}/read', [NetworkingMessageController::class, 'markRead']);
        Route::post('/channels/{uuid}/typing', [NetworkingMessageController::class, 'typing'])->middleware('throttle:customer-networking-typing');

        Route::post('/messages/{uuid}/reactions', [NetworkingReactionController::class, 'store']);
        Route::delete('/messages/{uuid}/reactions/{emoji}', [NetworkingReactionController::class, 'destroy']);
    });
});
