<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Rbac;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Kindharika\ApiStarter\Rbac\Contracts\RbacCheckerInterface;

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

    public function driver(): string
    {
        return (string) config('api-starter.rbac.driver', 'spatie');
    }

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

    public function hasRole(Authenticatable $user, string|array $roles): bool
    {
        return $this->checker()->hasRole($user, $roles);
    }

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
