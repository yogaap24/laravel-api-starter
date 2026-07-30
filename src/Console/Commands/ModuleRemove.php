<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Modules\ModulePaths;

class ModuleRemove extends Command
{
    protected $signature = 'module:remove
                            {module : Module name}
                            {name? : Resource name — omit to delete whole module}
                            {--force : Skip confirmation}
                            {--keep-migration : Keep migration files}';

    protected $description = 'Remove a module resource or entire module (module:* — not api:remove)';

    public function handle(): int
    {
        $module = Str::studly($this->argument('module'));
        $name = $this->argument('name') ? Str::studly((string) $this->argument('name')) : null;

        if (! ModulePaths::exists($module) && ! is_dir(ModulePaths::module($module))) {
            $this->error("Module [{$module}] not found.");

            return self::FAILURE;
        }

        if ($name === null) {
            return $this->removeModule($module);
        }

        return $this->removeResource($module, $name);
    }

    protected function removeModule(string $module): int
    {
        $dir = ModulePaths::module($module);

        if (! $this->option('force') && ! $this->confirm("Delete entire module [{$module}] at {$dir}?", false)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $tables = $this->discoverModuleTables($dir);
        $modelNames = $this->discoverModuleModels($dir);
        $deletedMigrations = [];
        if (! $this->option('keep-migration')) {
            foreach ($tables as $table) {
                $deletedMigrations = array_merge($deletedMigrations, $this->deleteMigrationsForTable($table, $dir));
            }
        }

        $prefix = ModulePaths::prefix($module);
        $tags = [$module, $prefix, Str::studly($prefix) . '/' . Str::studly($prefix)];
        $schemas = [];
        foreach ($modelNames as $modelName) {
            $tags[] = $module . '/' . $modelName;
            $tags[] = $modelName . '/' . $modelName;
            $schemas[] = $modelName;
            $schemas[] = $modelName . 'Store';
            $schemas[] = $modelName . 'Update';
        }

        File::deleteDirectory($dir);

        $this->removeFromOpenApiIndex(
            pathBases: [$prefix, $prefix . '/'],
            tags: array_values(array_unique($tags)),
            schemas: array_values(array_unique($schemas)),
        );
        $this->purgeLegacyOpenApiFragments();

        foreach ($deletedMigrations as $migration) {
            $this->line("Deleted: {$migration}");
        }
        if ($deletedMigrations !== []) {
            $this->warn('Deleted migration(s) — run migrate:rollback manually if already applied.');
        }

        $this->info("Module [{$module}] deleted (OpenAPI paths/tags/schemas cleaned).");

        return self::SUCCESS;
    }

    protected function removeResource(string $module, string $name): int
    {
        if (! $this->option('force') && ! $this->confirm("Delete [{$module}/{$name}] scaffold?", false)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $dir = ModulePaths::module($module);
        $route = Str::kebab(Str::pluralStudly($name));
        $primary = Str::studly($module) === Str::studly($name);
        $routeUri = $primary ? '' : $route;
        $table = Str::snake(Str::pluralStudly($name));
        $deleted = [];

        $candidates = [
            "{$dir}/Models/{$name}.php",
            "{$dir}/Http/Controllers/{$name}Controller.php",
            "{$dir}/Http/Resources/{$name}Resource.php",
            "{$dir}/Services/{$name}/{$name}Service.php",
            "{$dir}/Services/{$name}/{$name}ServiceInterface.php",
            "{$dir}/Http/Requests/{$name}/Store{$name}Request.php",
            "{$dir}/Http/Requests/{$name}/Update{$name}Request.php",
        ];

        foreach ($candidates as $path) {
            if (is_file($path) && File::delete($path)) {
                $deleted[] = $path;
            }
        }

        foreach ([
            "{$dir}/Services/{$name}",
            "{$dir}/Http/Requests/{$name}",
        ] as $d) {
            if (is_dir($d) && count(File::files($d)) === 0) {
                File::deleteDirectory($d);
                $deleted[] = $d . '/';
            }
        }

        foreach (['api.php', 'api-protected.php'] as $routeFile) {
            $path = "{$dir}/Routes/{$routeFile}";
            if (! is_file($path)) {
                continue;
            }
            $contents = (string) file_get_contents($path);
            $begin = preg_quote("// api-starter:resource:{$name}:begin", '/');
            $end = preg_quote("// api-starter:resource:{$name}:end", '/');
            $updated = preg_replace('/' . $begin . '.*?' . $end . '\n?/s', '', $contents) ?? $contents;
            if ($routeUri !== '') {
                $pattern = '/^Route::(?:middleware\([^)]+\)->)?apiResource\(\'' . preg_quote($routeUri, '/') . '\'.*\n?/m';
                $updated = preg_replace($pattern, '', $updated) ?? $updated;
            }
            foreach ([$route, Str::kebab(Str::studly($name))] as $legacy) {
                $pattern = '/^Route::(?:middleware\([^)]+\)->)?apiResource\(\'' . preg_quote($legacy, '/') . '\'.*\n?/m';
                $updated = preg_replace($pattern, '', $updated) ?? $updated;
            }
            if (is_string($updated) && $updated !== $contents) {
                file_put_contents($path, $updated);
                $deleted[] = "route:{$routeFile}:{$name}";
            }
        }

        if (! $this->option('keep-migration')) {
            $migrations = $this->deleteMigrationsForTable($table, $dir);
            $deleted = array_merge($deleted, $migrations);
            if ($migrations !== []) {
                $this->warn('Deleted migration — run migrate:rollback manually if already applied.');
            }
        }

        $modulePrefix = ModulePaths::prefix($module);
        $pathNeedle = $primary ? $modulePrefix : ($modulePrefix . '/' . $routeUri);
        $tag = $primary ? $module : ($module . '/' . $name);
        $this->removeFromOpenApiIndex(
            pathBases: array_filter([
                $pathNeedle,
                $primary ? $modulePrefix . '/' . $route : null,
                $primary ? $modulePrefix . '/' . Str::kebab(Str::studly($name)) : null,
            ]),
            tags: [$tag, $module . '/' . $name, $name . '/' . $name],
            schemas: [$name, $name . 'Store', $name . 'Update'],
        );
        $this->purgeLegacyOpenApiFragments();
        $deleted[] = 'openapi.json paths/schemas for ' . $tag;

        if ($deleted === []) {
            $this->warn("No files found for [{$module}/{$name}].");
        } else {
            foreach ($deleted as $item) {
                $this->line("Deleted: {$item}");
            }
            $this->info("Removed [{$module}/{$name}].");
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string> table names
     */
    protected function discoverModuleTables(string $moduleDir): array
    {
        $tables = [];
        foreach ($this->discoverModuleModels($moduleDir) as $name) {
            $tables[] = Str::snake(Str::pluralStudly($name));
        }

        return array_values(array_unique($tables));
    }

    /**
     * @return list<string> Studly model class names
     */
    protected function discoverModuleModels(string $moduleDir): array
    {
        $models = [];
        foreach (File::glob($moduleDir . '/Models/*.php') ?: [] as $file) {
            $name = pathinfo((string) $file, PATHINFO_FILENAME);
            if ($name === '' || $name === 'Model') {
                continue;
            }
            $models[] = $name;
        }

        return array_values(array_unique($models));
    }

    /**
     * Delete create_* migrations for a table from standard + legacy module paths.
     *
     * @return list<string> deleted paths
     */
    protected function deleteMigrationsForTable(string $table, ?string $moduleDir = null): array
    {
        $deleted = [];
        $migrationDir = config('api-starter.paths.migration', database_path('migrations'));

        $patterns = [
            $migrationDir . "/*_create_{$table}_table.php",
            $migrationDir . "/*_create_{$table}.php",
        ];
        if ($moduleDir !== null) {
            $patterns[] = $moduleDir . "/Database/Migrations/*_create_{$table}_table.php";
            $patterns[] = $moduleDir . "/Database/Migrations/*_create_{$table}.php";
        }

        foreach ($patterns as $pattern) {
            foreach (glob($pattern) ?: [] as $migration) {
                if (is_file($migration) && File::delete($migration)) {
                    $deleted[] = $migration;
                }
            }
        }

        return $deleted;
    }

    /**
     * @param  list<string>  $pathBases
     * @param  list<string>  $tags
     * @param  list<string>  $schemas
     */
    protected function removeFromOpenApiIndex(array $pathBases = [], array $tags = [], array $schemas = []): void
    {
        $indexPath = config('api-starter.paths.openapi', base_path('storage/api-docs')) . '/openapi.json';
        if (! is_file($indexPath)) {
            return;
        }

        $index = json_decode((string) file_get_contents($indexPath), true);
        if (! is_array($index)) {
            return;
        }

        foreach (array_keys($index['paths'] ?? []) as $pathKey) {
            $trimmed = ltrim((string) $pathKey, '/');
            foreach ($pathBases as $base) {
                $base = rtrim(ltrim((string) $base, '/'), '/');
                if ($base === '') {
                    continue;
                }
                if ($trimmed === $base || str_starts_with($trimmed, $base . '/')) {
                    unset($index['paths'][$pathKey]);
                    break;
                }
            }
        }

        if ($tags !== []) {
            $index['tags'] = array_values(array_filter(
                $index['tags'] ?? [],
                static fn ($t): bool => ! in_array($t['name'] ?? null, $tags, true)
            ));
        }

        foreach ($schemas as $schema) {
            unset($index['components']['schemas'][$schema]);
        }

        file_put_contents($indexPath, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }

    protected function removeOpenApiPathsContaining(string $needle): void
    {
        $this->removeFromOpenApiIndex(pathBases: [$needle]);
    }

    protected function purgeLegacyOpenApiFragments(): void
    {
        $dir = config('api-starter.paths.openapi', base_path('storage/api-docs'));
        if (! is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*.openapi.json') ?: [] as $file) {
            if (is_file($file)) {
                File::delete($file);
            }
        }
    }
}
