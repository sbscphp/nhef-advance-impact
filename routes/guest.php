<?php

use App\Http\Controllers\v1\Guest\Fundraising\GuestDonationController;
use App\Http\Controllers\v1\Guest\Fundraising\GuestPledgeController;
use Illuminate\Support\Facades\Route;

// Guest donor flow (BRD ENT-04): no auth:sanctum anywhere in this file. Campaign browsing and
// payment verification are shared with the customer flow — see routes/public.php.
Route::prefix('v1/guest')->group(function () {
    Route::post('pledges', [GuestPledgeController::class, 'store'])->middleware('throttle:guest-pledge-create');
    Route::post('donations', [GuestDonationController::class, 'store'])->middleware('throttle:guest-donation-create');
});
