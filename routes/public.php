<?php

use App\Http\Controllers\v1\Fundraising\CampaignController;
use App\Http\Controllers\v1\Fundraising\DonationPaymentController;
use App\Http\Controllers\v1\Fundraising\PaymentController;
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
});
