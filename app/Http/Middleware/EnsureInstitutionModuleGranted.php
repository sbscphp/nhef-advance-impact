<?php

namespace App\Http\Middleware;

use App\Enums\ModuleEnums;
use App\Helpers\PermissionModuleMapper;
use App\Models\Institution;
use App\Repositories\Contracts\InstitutionModule\InstitutionModuleRepositoryInterface;
use App\Responser\JsonResponser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the AdvanceImpact "System Access Allowable" grant set at invite time
 * ({@see InstitutionModuleRepositoryInterface}) on top of the acting admin's own role
 * permissions - a role can permit an action everywhere, but the institution's own module grant
 * decides whether it's available on this particular institution. Global (registered in
 * bootstrap/app.php, not per-route) so every existing and future `permission:` route is covered
 * automatically: it reads the matched route's own `permission:x` middleware to find what's being
 * checked, so there's nothing to wire up per-endpoint.
 *
 * Runs after ApplyTenantContext, which sets {@see Institution::current()} - a no-op for NHEF
 * admins (no institution) and for any module outside
 * {@see ModuleEnums::institutionGateableModuleKeys()}, since the structural basics (Dashboard,
 * Constituent Management, User Management, Reporting, Audit Trail, Notification, System
 * Configuration) are never gated this way.
 */
class EnsureInstitutionModuleGranted
{
    public function __construct(private readonly InstitutionModuleRepositoryInterface $institutionModuleRepository) {}

    public function handle(Request $request, Closure $next): Response
    {
        $institution = Institution::current();

        if (! $institution instanceof Institution) {
            return $next($request);
        }

        $permissionName = $this->requiredPermission($request);
        $moduleKey = $permissionName === null ? null : PermissionModuleMapper::moduleKeyForPermissionName($permissionName);

        if ($moduleKey === null || ! in_array($moduleKey, ModuleEnums::institutionGateableModuleKeys(), true)) {
            return $next($request);
        }

        $grantedModules = $this->institutionModuleRepository->keysForInstitution($institution->id);

        if (! $grantedModules->contains($moduleKey)) {
            return JsonResponser::send(true, 'This module is not available on your institution\'s account.', null, 403);
        }

        return $next($request);
    }

    private function requiredPermission(Request $request): ?string
    {
        $route = $request->route();

        if ($route === null) {
            return null;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (str_starts_with($middleware, 'permission:')) {
                return substr($middleware, strlen('permission:'));
            }
        }

        return null;
    }
}
