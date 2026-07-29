<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Rbac;

use Illuminate\Contracts\Auth\Authenticatable;
use Kindharika\ApiStarter\Rbac\Contracts\RbacCheckerInterface;

/**
 * Adapter for spatie/laravel-permission (or any model with hasRole/hasPermissionTo).
 */
class SpatieRbacChecker implements RbacCheckerInterface
{
    public function hasRole(Authenticatable $user, string|array $roles): bool
    {
        if (! method_exists($user, 'hasRole')) {
            return false;
        }

        return (bool) $user->hasRole($roles);
    }

    public function hasPermission(Authenticatable $user, string|array $permissions): bool
    {
        if (method_exists($user, 'hasAnyPermission')) {
            return (bool) $user->hasAnyPermission((array) $permissions);
        }

        if (! method_exists($user, 'hasPermissionTo')) {
            return false;
        }

        foreach ((array) $permissions as $permission) {
            if ($user->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }
}
