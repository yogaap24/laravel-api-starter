<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Modules;

use Illuminate\Support\Str;

class ModuleManifest
{
    /**
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $enabled = true,
        public readonly string $prefix = '',
        public readonly array $roles = [],
        public readonly array $permissions = [],
    ) {}

    public static function load(string $module): ?self
    {
        $path = ModulePaths::module($module) . '/module.json';

        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            return null;
        }

        $rbac = is_array($data['rbac'] ?? null) ? $data['rbac'] : [];

        return new self(
            name: (string) ($data['name'] ?? Str::studly($module)),
            enabled: (bool) ($data['enabled'] ?? true),
            prefix: (string) ($data['prefix'] ?? Str::kebab(Str::studly($module))),
            roles: array_values(array_filter(array_map('strval', $rbac['roles'] ?? []))),
            permissions: array_values(array_filter(array_map('strval', $rbac['permissions'] ?? []))),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'enabled' => $this->enabled,
            'prefix' => $this->prefix,
            'rbac' => [
                'roles' => $this->roles,
                'permissions' => $this->permissions,
            ],
        ];
    }

    public function write(string $module): void
    {
        $dir = ModulePaths::module($module);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $dir . '/module.json',
            json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }
}
