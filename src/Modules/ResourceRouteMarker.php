<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Modules;

/**
 * Sole owner of the `// api-starter:resource:{Name}:begin/end` route marker format.
 *
 * Every producer and consumer of that format goes through this class, so the
 * writer and the readers can never drift apart:
 *   - Console\Commands\ModuleScaffold  — writes blocks, strips them on overwrite
 *   - Console\Commands\ModuleRemove    — strips blocks when a resource is removed
 *   - ApiStarterServiceProvider        — reads blocks at runtime to discover protected paths
 */
final class ResourceRouteMarker
{
    public static function begin(string $resourceName): string
    {
        return "// api-starter:resource:{$resourceName}:begin";
    }

    public static function end(string $resourceName): string
    {
        return "// api-starter:resource:{$resourceName}:end";
    }

    public static function wrap(string $resourceName, string $body): string
    {
        return self::begin($resourceName) . "\n" . $body . "\n" . self::end($resourceName);
    }

    /**
     * Matches one named block, including its trailing newline.
     */
    public static function blockPattern(string $resourceName): string
    {
        return '/'
            . preg_quote(self::begin($resourceName), '/')
            . '.*?'
            . preg_quote(self::end($resourceName), '/')
            . '\n?/s';
    }

    /**
     * Matches every block via backreference. Group 1 = resource name, group 2 = block body.
     */
    public static function allBlocksPattern(): string
    {
        return '/\/\/ api-starter:resource:([A-Za-z0-9_]+):begin(.*?)\/\/ api-starter:resource:\1:end/s';
    }

    /**
     * Matches a legacy unmarked `Route::apiResource('{uri}', ...)` line.
     */
    public static function apiResourceLinePattern(string $routeUri): string
    {
        return '/^Route::(?:middleware\([^)]+\)->)?apiResource\(\'' . preg_quote($routeUri, '/') . '\'.*\n?/m';
    }

    /**
     * Harvests the URI from any apiResource call. Group 1 = URI.
     */
    public static function apiResourceUriPattern(): string
    {
        return "/apiResource\\('([^']+)'/";
    }

    /**
     * @param  list<string>  $legacyUris
     */
    public static function strip(string $contents, string $resourceName, array $legacyUris): string
    {
        $updated = preg_replace(self::blockPattern($resourceName), '', $contents) ?? $contents;

        foreach (array_unique(array_filter($legacyUris)) as $legacy) {
            $updated = preg_replace(self::apiResourceLinePattern($legacy), '', $updated) ?? $updated;
        }

        return $updated;
    }
}
