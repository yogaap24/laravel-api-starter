<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Support;

/**
 * Opt-in SSO / Socialite config helpers.
 *
 * Env: API_STARTER_SOCIAL, API_STARTER_SOCIAL_PROVIDERS, API_STARTER_SOCIAL_STATELESS
 */
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

    /**
     * Whether {provider} is in the allowlist (case-insensitive).
     */
    public static function isAllowed(string $provider): bool
    {
        return in_array(strtolower($provider), self::providers(), true);
    }

    /**
     * Use Socialite::stateless() for API / SPA flows (no session state).
     */
    public static function stateless(): bool
    {
        return (bool) config('api-starter.social.stateless', true);
    }
}
