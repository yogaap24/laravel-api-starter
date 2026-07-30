<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Rbac\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Pluggable RBAC checker — implement for custom drivers.
 *
 * Register via config api-starter.rbac.driver=custom and
 * api-starter.rbac.custom.checker = Your\\Checker::class
 */
interface RbacCheckerInterface
{
    /**
     * @param  string|list<string>  $roles  Role name(s); typically ANY match
     */
    public function hasRole(Authenticatable $user, string|array $roles): bool;

    /**
     * @param  string|list<string>  $permissions  Permission name(s); typically ANY match
     */
    public function hasPermission(Authenticatable $user, string|array $permissions): bool;
}
