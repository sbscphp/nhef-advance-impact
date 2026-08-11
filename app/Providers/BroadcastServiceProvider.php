<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /*
         * Registered directly via Broadcast::routes(), not inside routes/api.php, so this does
         * not pick up the global "api" middleware group (RequestResponseEncryptionMiddleware
         * expects an X-ClientKey header and a custom envelope, which would break the Pusher JS
         * client's plain-JSON auth request). Only Sanctum auth is required here.
         */
        Broadcast::routes(['middleware' => ['auth:sanctum'], 'prefix' => 'api/v1']);

        require base_path('routes/channels.php');
    }
}
