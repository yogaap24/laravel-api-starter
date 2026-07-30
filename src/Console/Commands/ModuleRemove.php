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

        $prefix = ModulePaths::prefix($module);
        File::deleteDirectory($dir);

        // Clean OpenAPI module files
        $openapiDir = config('api-starter.paths.openapi', base_path('storage/api-docs'));
        foreach (File::glob($openapiDir . "/module-{$prefix}-*.openapi.json") ?: [] as $file) {
            File::delete($file);
            $this->removeOpenApiPathsContaining($prefix . '/');
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
            $pattern = '/^Route::(?:middleware\([^)]+\)->)?apiResource\(\'' . preg_quote($route, '/') . '\'.*\n?/m';
            $updated = preg_replace($pattern, '', $contents);
            if (is_string($updated) && $updated !== $contents) {
                file_put_contents($path, $updated);
                $deleted[] = "route:{$routeFile}:{$route}";
            }
        }

        if (! $this->option('keep-migration')) {
            $migrationDir = config('api-starter.paths.migration', database_path('migrations'));
            foreach (File::glob($migrationDir . "/*_create_{$table}_table.php") ?: [] as $migration) {
                if (File::delete($migration)) {
                    $deleted[] = $migration;
                    $this->warn("Deleted migration — rollback manually if already applied.");
                }
            }
            // BC: clean legacy per-module migrations if any remain
            foreach (File::glob("{$dir}/Database/Migrations/*_create_{$table}_table.php") ?: [] as $migration) {
                if (File::delete($migration)) {
                    $deleted[] = $migration;
                }
            }
        }

        $modulePrefix = ModulePaths::prefix($module);
        $openapi = config('api-starter.paths.openapi', base_path('storage/api-docs'))
            . "/module-{$modulePrefix}-{$route}.openapi.json";
        if (is_file($openapi) && File::delete($openapi)) {
            $deleted[] = $openapi;
        }
        $this->removeOpenApiPathsContaining($modulePrefix . '/' . $route);

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

    protected function removeOpenApiPathsContaining(string $needle): void
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
            if (str_contains((string) $pathKey, $needle)) {
                unset($index['paths'][$pathKey]);
            }
        }

        file_put_contents($indexPath, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }
}
