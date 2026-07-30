<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Modules;

use Illuminate\Support\Str;

/**
 * Path / namespace helpers for app/Modules/{Name}.
 */
class ModulePaths
{
    /**
     * Absolute path to Modules root (default: app/Modules).
     */
    public static function root(): string
    {
        return (string) config('api-starter.modules.path', app_path('Modules'));
    }

    /**
     * Absolute path to one module directory.
     *
     * @param  string  $module  Studly or any case — normalized to Studly
     */
    public static function module(string $module): string
    {
        return self::root() . '/' . Str::studly($module);
    }

    /**
     * PSR-4 namespace for a module, e.g. App\Modules\Blog.
     */
    public static function namespace(string $module): string
    {
        $root = rtrim((string) config('api-starter.namespace', 'App'), '\\');
        $base = trim((string) config('api-starter.modules.namespace', 'Modules'), '\\');

        return $root . '\\' . $base . '\\' . Str::studly($module);
    }

    /**
     * URL prefix segment for the module (from module.json or kebab name).
     */
    public static function prefix(string $module): string
    {
        $manifest = ModuleManifest::load($module);

        if ($manifest !== null && $manifest->prefix !== '') {
            return $manifest->prefix;
        }

        return Str::kebab(Str::studly($module));
    }

    public static function exists(string $module): bool
    {
        return is_dir(self::module($module)) && is_file(self::module($module) . '/module.json');
    }

    /**
     * @return list<string>  Studly module directory names that have module.json
     */
    public static function list(): array
    {
        $root = self::root();

        if (! is_dir($root)) {
            return [];
        }

        $modules = [];

        foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (is_file($dir . '/module.json')) {
                $modules[] = basename($dir);
            }
        }

        sort($modules);

        return $modules;
    }
}
