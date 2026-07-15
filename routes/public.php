<?php

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
});