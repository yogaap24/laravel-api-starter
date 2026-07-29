<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Rbac\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface RbacCheckerInterface
{
    public function hasRole(Authenticatable $user, string|array $roles): bool;

    public function hasPermission(Authenticatable $user, string|array $permissions): bool;
}
