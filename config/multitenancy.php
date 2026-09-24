<?php

use App\Models\Institution;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\CallQueuedClosure;
use Spatie\Multitenancy\Actions\ForgetCurrentTenantAction;
use Spatie\Multitenancy\Actions\MakeQueueTenantAwareAction;
use Spatie\Multitenancy\Actions\MakeTenantCurrentAction;
use Spatie\Multitenancy\Actions\MigrateTenantAction;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Spatie\Multitenancy\Jobs\TenantAware;

/*
| Institutions are the tenants; NHEF operates in the landlord context (no current
| tenant). All tenants share one database and are isolated by query scopes
| (see App\Models\Concerns\BelongsToTenant), so no database-switching task is used.
| The current tenant is set per request by App\Http\Middleware\ApplyTenantContext
| from the authenticated admin, never from a client-supplied header.
*/
return [
    'tenant_finder' => null,

    'tenant_artisan_search_fields' => [
        'id',
    ],

    'switch_tenant_tasks' => [],

    'tenant_model' => Institution::class,

    'queues_are_tenant_aware_by_default' => false,

    'tenant_database_connection_name' => null,

    'landlord_database_connection_name' => null,

    'current_tenant_context_key' => 'tenantId',

    'current_tenant_container_key' => 'currentTenant',

    'shared_routes_cache' => false,

    'actions' => [
        'make_tenant_current_action' => MakeTenantCurrentAction::class,
        'forget_current_tenant_action' => ForgetCurrentTenantAction::class,
        'make_queue_tenant_aware_action' => MakeQueueTenantAwareAction::class,
        'migrate_tenant' => MigrateTenantAction::class,
    ],

    'queueable_to_job' => [
        SendQueuedMailable::class => 'mailable',
        SendQueuedNotifications::class => 'notification',
        CallQueuedClosure::class => 'closure',
        CallQueuedListener::class => 'class',
        BroadcastEvent::class => 'event',
    ],

    'tenant_aware_interface' => TenantAware::class,

    'not_tenant_aware_interface' => NotTenantAware::class,

    'tenant_aware_jobs' => [],

    'not_tenant_aware_jobs' => [],
];
