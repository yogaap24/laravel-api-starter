<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Rbac;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Kindharika\ApiStarter\Rbac\Contracts\RbacCheckerInterface;

/**
 * Uses Laravel Gate abilities as "permissions". Roles resolve via config map or Gate.
 */
class GateRbacChecker implements RbacCheckerInterface
{
    public function __construct(
        protected Gate $gate,
    ) {}

    public function hasRole(Authenticatable $user, string|array $roles): bool
    {
        $map = config('api-starter.rbac.gate.role_abilities', []);

        foreach ((array) $roles as $role) {
            $ability = is_array($map) ? ($map[$role] ?? 'role:' . $role) : 'role:' . $role;

            if ($this->gate->forUser($user)->check($ability)) {
                return true;
            }
        }

        return false;
    }

    public function hasPermission(Authenticatable $user, string|array $permissions): bool
    {
        foreach ((array) $permissions as $permission) {
            if ($this->gate->forUser($user)->check($permission)) {
                return true;
            }
        }

        return false;
    }
}
