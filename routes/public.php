<?php

use App\Http\Controllers\v1\Events\EventController;
use App\Http\Controllers\v1\Events\EventRegistrationPaymentController;
use App\Http\Controllers\v1\Fundraising\CampaignController;
use App\Http\Controllers\v1\Fundraising\DonationPaymentController;
use App\Http\Controllers\v1\Fundraising\DonationReceiptController;
use App\Http\Controllers\v1\Fundraising\PaymentController;
use App\Http\Controllers\v1\Recognition\LeaderboardController;
use App\Http\Controllers\v1\Webhooks\PaystackWebhookController;
use App\Http\Controllers\v1\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'app' => config('app.name', 'nhef-nexus'),
            'timestamp' => now()->toISOString(),
        ]);
    });

    Route::post('webhooks/paystack', [PaystackWebhookController::class, 'handle']);
    Route::post('webhooks/stripe', [StripeWebhookController::class, 'handle']);

    // Campaign browsing and payment verification are shared by the authenticated customer
    // flow and the guest donor flow (routes/guest.php); no account needed for either.
    Route::prefix('campaigns')->group(function () {
        Route::get('/', [CampaignController::class, 'index']);
        Route::get('/{uuid}', [CampaignController::class, 'show']);
    });

    Route::post('pledges/payments/{reference}/verify', [PaymentController::class, 'verify']);
    Route::post('donations/payments/{reference}/verify', [DonationPaymentController::class, 'verify']);

    // Event browsing and payment verification are shared by the authenticated customer flow
    // and the guest registration flow (routes/guest.php); no account needed for either.
    Route::prefix('events')->group(function () {
        Route::get('/', [EventController::class, 'index']);
        Route::get('/{uuid}', [EventController::class, 'show']);
    });

    Route::post('events/registrations/payments/{reference}/verify', [EventRegistrationPaymentController::class, 'verify']);

    // Matches RequestResponseEncryptionMiddleware::BYPASS_REGEX: opened from an emailed link
    // with no X-ClientKey header, so the `signed` middleware's signature is the credential.
    Route::get('public/receipts/{uuid}/download', [DonationReceiptController::class, 'download'])
        ->middleware('signed')
        ->name('receipts.download');

    Route::get('public/recognition/leaderboard', [LeaderboardController::class, 'index']);
});
