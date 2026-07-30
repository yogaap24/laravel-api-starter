<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Rbac;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Kindharika\ApiStarter\Rbac\Contracts\RbacCheckerInterface;

/**
 * Resolves RBAC driver from config and delegates role/permission checks.
 *
 * Drivers: spatie | gate | custom | null (fail-closed when enabled).
 * When rbac.enabled=false, checker always returns true (middleware no-op).
 */
class RbacManager
{
    protected ?RbacCheckerInterface $checker = null;

    public function __construct(
        protected Gate $gate,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('api-starter.rbac.enabled', false);
    }

    /**
     * Configured driver name.
     *
     * @return string  spatie|gate|custom|null|…
     */
    public function driver(): string
    {
        return (string) config('api-starter.rbac.driver', 'spatie');
    }

    /**
     * Lazy-resolved checker for the active driver.
     */
    public function checker(): RbacCheckerInterface
    {
        if ($this->checker !== null) {
            return $this->checker;
        }

        if (! $this->enabled()) {
            return $this->checker = new class implements RbacCheckerInterface
            {
                public function hasRole(Authenticatable $user, string|array $roles): bool
                {
                    return true;
                }

                public function hasPermission(Authenticatable $user, string|array $permissions): bool
                {
                    return true;
                }
            };
        }

        return $this->checker = match ($this->driver()) {
            'spatie' => new SpatieRbacChecker,
            'gate' => new GateRbacChecker($this->gate),
            'custom' => $this->resolveCustom(),
            default => new NullRbacChecker,
        };
    }

    /**
     * @param  string|list<string>  $roles
     */
    public function hasRole(Authenticatable $user, string|array $roles): bool
    {
        return $this->checker()->hasRole($user, $roles);
    }

    /**
     * @param  string|list<string>  $permissions
     */
    public function hasPermission(Authenticatable $user, string|array $permissions): bool
    {
        return $this->checker()->hasPermission($user, $permissions);
    }

    protected function resolveCustom(): RbacCheckerInterface
    {
        $class = config('api-starter.rbac.custom.checker');

        if (! is_string($class) || $class === '' || ! class_exists($class)) {
            return new NullRbacChecker;
        }

        $instance = app($class);

        if (! $instance instanceof RbacCheckerInterface) {
            return new NullRbacChecker;
        }

        return new CustomRbacChecker($instance);
    }
}
