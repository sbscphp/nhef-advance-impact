<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /*
         * Registered directly, not inside routes/api.php, so it skips the "api" middleware group
         * (whose encryption envelope would break the Pusher JS client's plain-JSON auth request).
         */
        Broadcast::routes(['middleware' => ['auth:sanctum'], 'prefix' => 'api/v1']);

        require base_path('routes/channels.php');
    }
}
