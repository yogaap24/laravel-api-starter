<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Modules;

use Illuminate\Support\Str;

class ModulePaths
{
    public static function root(): string
    {
        return (string) config('api-starter.modules.path', app_path('Modules'));
    }

    public static function module(string $module): string
    {
        return self::root() . '/' . Str::studly($module);
    }

    public static function namespace(string $module): string
    {
        $root = rtrim((string) config('api-starter.namespace', 'App'), '\\');
        $base = trim((string) config('api-starter.modules.namespace', 'Modules'), '\\');

        return $root . '\\' . $base . '\\' . Str::studly($module);
    }

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
     * @return list<string>
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
