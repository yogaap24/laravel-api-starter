<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Rbac;

use Illuminate\Contracts\Auth\Authenticatable;
use Kindharika\ApiStarter\Rbac\Contracts\RbacCheckerInterface;

class CustomRbacChecker implements RbacCheckerInterface
{
    public function __construct(
        protected RbacCheckerInterface $inner,
    ) {}

    public function hasRole(Authenticatable $user, string|array $roles): bool
    {
        return $this->inner->hasRole($user, $roles);
    }

    public function hasPermission(Authenticatable $user, string|array $permissions): bool
    {
        return $this->inner->hasPermission($user, $permissions);
    }
}
