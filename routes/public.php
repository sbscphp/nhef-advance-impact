<?php

use App\Http\Controllers\v1\Events\EventController;
use App\Http\Controllers\v1\Events\EventRegistrationPaymentController;
use App\Http\Controllers\v1\Fundraising\CampaignController;
use App\Http\Controllers\v1\Fundraising\DonationPaymentController;
use App\Http\Controllers\v1\Fundraising\DonationReceiptController;
use App\Http\Controllers\v1\Fundraising\PaymentController;
use App\Http\Controllers\v1\Recognition\LeaderboardController;
use App\Http\Controllers\v1\Webhooks\PaystackWebhookController;
use Illuminate\Support\Facades\Route;

// Public routes; feature branch: public
Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'app' => config('app.name', 'nhef-nexus'),
            'timestamp' => now()->toISOString(),
        ]);
    });

    Route::post('webhooks/paystack', [PaystackWebhookController::class, 'handle']);

    // Campaign browsing and payment verification are shared by the authenticated customer
    // flow and the guest donor flow (routes/guest.php) — no account needed for either.
    Route::prefix('campaigns')->group(function () {
        Route::get('/', [CampaignController::class, 'index']);
        Route::get('/{uuid}', [CampaignController::class, 'show']);
    });

    Route::post('pledges/payments/{reference}/verify', [PaymentController::class, 'verify']);
    Route::post('donations/payments/{reference}/verify', [DonationPaymentController::class, 'verify']);

    // Event browsing and payment verification are shared by the authenticated customer flow
    // and the guest registration flow (routes/guest.php) — no account needed for either.
    Route::prefix('events')->group(function () {
        Route::get('/', [EventController::class, 'index']);
        Route::get('/{uuid}', [EventController::class, 'show']);
    });

    Route::post('events/registrations/payments/{reference}/verify', [EventRegistrationPaymentController::class, 'verify']);

    // Matches the reserved pattern in RequestResponseEncryptionMiddleware::BYPASS_REGEX — this
    // is opened directly from an emailed receipt link, so it can't carry an X-ClientKey header;
    // the `signed` middleware's signature is the credential instead.
    Route::get('public/receipts/{uuid}/download', [DonationReceiptController::class, 'download'])
        ->middleware('signed')
        ->name('receipts.download');

    Route::get('public/recognition/leaderboard', [LeaderboardController::class, 'index']);
});
