<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Console\InteractsWithStubs;
use Kindharika\ApiStarter\Console\ManagesOpenApiDocument;
use Kindharika\ApiStarter\Modules\ModulePaths;
use Kindharika\ApiStarter\Modules\ResourceRouteMarker;

class ModuleRemove extends Command
{
    use InteractsWithStubs;
    use ManagesOpenApiDocument;

    protected $signature = 'module:remove
                            {module : Module name}
                            {name? : Resource name — omit to delete whole module}
                            {--force : Skip confirmation}
                            {--keep-migration : Keep migration files}
                            {--openapi-only : Only clean openapi.json (module dir may already be gone)}';

    protected $description = 'Remove a module resource or entire module (module:* — not api:remove)';

    public function handle(): int
    {
        $module = Str::studly($this->argument('module'));
        $name = $this->argument('name') ? Str::studly((string) $this->argument('name')) : null;
        $exists = ModulePaths::exists($module) || is_dir(ModulePaths::module($module));

        if ($this->option('openapi-only') || ! $exists) {
            if (! $exists && ! $this->option('openapi-only')) {
                $this->warn("Module [{$module}] not found on disk — cleaning OpenAPI only.");
            }

            return $this->cleanOpenApiOnly($module, $name);
        }

        if ($name === null) {
            return $this->removeModule($module);
        }

        return $this->removeResource($module, $name);
    }

    protected function cleanOpenApiOnly(string $module, ?string $name): int
    {
        $prefix = ModulePaths::prefix($module);

        if ($name === null) {
            $stats = $this->removeOpenApiArtifacts(
                pathBases: [$prefix],
                tags: [$module, $prefix, Str::studly($prefix) . '/' . Str::studly($prefix)],
                schemas: [],
            );
        } else {
            $primary = Str::studly($module) === Str::studly($name);
            $route = Str::kebab(Str::pluralStudly($name));
            $pathNeedle = $primary ? $prefix : ($prefix . '/' . $route);
            $tag = $primary ? $module : ($module . '/' . $name);
            $stats = $this->removeOpenApiArtifacts(
                pathBases: array_filter([
                    $pathNeedle,
                    $primary ? $prefix . '/' . $route : null,
                ]),
                tags: [$tag, $module . '/' . $name, $name . '/' . $name],
                schemas: [$name],
            );
        }

        $this->reportOpenApiCleanup($stats);

        return self::SUCCESS;
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
            $tags[] = $modelName;
            $schemas[] = $modelName;
        }

        File::deleteDirectory($dir);

        $stats = $this->removeOpenApiArtifacts(
            pathBases: [$prefix],
            tags: array_values(array_unique($tags)),
            schemas: array_values(array_unique($schemas)),
        );
        $this->reportOpenApiCleanup($stats);

        foreach ($deletedMigrations as $migration) {
            $this->line("Deleted: {$migration}");
        }
        if ($deletedMigrations !== []) {
            $this->warn('Deleted migration(s) — run migrate:rollback manually if already applied.');
        }

        $this->info("Module [{$module}] deleted.");

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
            $updated = ResourceRouteMarker::strip($contents, $name, [
                $routeUri,
                $route,
                Str::kebab(Str::studly($name)),
            ]);
            if ($updated !== $contents) {
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
        $stats = $this->removeOpenApiArtifacts(
            pathBases: array_filter([
                $pathNeedle,
                $primary ? $modulePrefix . '/' . $route : null,
                $primary ? $modulePrefix . '/' . Str::kebab(Str::studly($name)) : null,
            ]),
            tags: [$tag, $module . '/' . $name, $name . '/' . $name, $name],
            schemas: [$name],
        );
        $this->reportOpenApiCleanup($stats);
        $deleted[] = 'openapi.json';

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
     * @param  array{paths: int, tags: int, schemas: int}  $stats
     */
    protected function reportOpenApiCleanup(array $stats): void
    {
        $this->info(sprintf(
            'OpenAPI cleaned: %d path(s), %d tag(s), %d schema(s)',
            $stats['paths'],
            $stats['tags'],
            $stats['schemas'],
        ));
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

        // Fallback: Controllers/{Name}Controller.php
        if ($models === []) {
            foreach (File::glob($moduleDir . '/Http/Controllers/*Controller.php') ?: [] as $file) {
                $base = pathinfo((string) $file, PATHINFO_FILENAME);
                if (! str_ends_with($base, 'Controller')) {
                    continue;
                }
                $name = substr($base, 0, -strlen('Controller'));
                if ($name !== '') {
                    $models[] = $name;
                }
            }
        }

        return array_values(array_unique($models));
    }

    /**
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
}
