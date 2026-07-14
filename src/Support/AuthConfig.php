<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Support;

class AuthConfig
{
    /**
     * Global default for new scaffolds (placement). Opt-in via API_STARTER_AUTH.
     */
    public static function enabled(): bool
    {
        return (bool) config('api-starter.auth.enabled', false);
    }

    /**
     * @return list<string>
     */
    public static function middleware(): array
    {
        $custom = config('api-starter.auth.middleware');

        if (is_array($custom) && $custom !== []) {
            return array_values($custom);
        }

        if (is_string($custom) && $custom !== '') {
            return [$custom];
        }

        $guard = (string) config('api-starter.auth.guard', 'sanctum');

        return ['auth:' . $guard];
    }

    /**
     * @return list<string>
     */
    public static function baseMiddleware(): array
    {
        $base = config('api-starter.route_middleware', ['api']);

        return array_values(is_array($base) ? $base : [$base]);
    }

    /**
     * Middleware for protected route directory — always includes Sanctum/auth.
     *
     * @return list<string>
     */
    public static function protectedMiddleware(): array
    {
        return array_values(array_unique([
            ...self::baseMiddleware(),
            ...self::middleware(),
        ]));
    }

    /**
     * Middleware for public route directory — never includes auth.
     *
     * @return list<string>
     */
    public static function publicMiddleware(): array
    {
        return self::baseMiddleware();
    }
}
