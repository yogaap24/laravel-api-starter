<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Support;

class SocialConfig
{
    public static function enabled(): bool
    {
        return (bool) config('api-starter.social.enabled', false);
    }

    /**
     * Allowlisted Socialite drivers (google, github, azure, …).
     *
     * @return list<string>
     */
    public static function providers(): array
    {
        $providers = config('api-starter.social.providers', ['google']);

        if (! is_array($providers)) {
            return ['google'];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($p) => strtolower(trim((string) $p)),
            $providers
        ))));
    }

    public static function isAllowed(string $provider): bool
    {
        return in_array(strtolower($provider), self::providers(), true);
    }

    public static function stateless(): bool
    {
        return (bool) config('api-starter.social.stateless', true);
    }
}
