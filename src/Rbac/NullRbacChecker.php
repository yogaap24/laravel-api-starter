<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Rbac;

use Illuminate\Contracts\Auth\Authenticatable;
use Kindharika\ApiStarter\Rbac\Contracts\RbacCheckerInterface;

/**
 * Fail-closed when RBAC is enabled but no driver is usable.
 */
class NullRbacChecker implements RbacCheckerInterface
{
    public function hasRole(Authenticatable $user, string|array $roles): bool
    {
        return false;
    }

    public function hasPermission(Authenticatable $user, string|array $permissions): bool
    {
        return false;
    }
}
