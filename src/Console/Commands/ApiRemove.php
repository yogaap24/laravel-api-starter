<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ApiRemove extends Command
{
    protected $signature = 'api:remove
                            {name : The resource name (e.g. Post or CobaAuth)}
                            {--force : Do not ask for confirmation}
                            {--keep-migration : Keep migration files}';

    protected $description = 'Remove a scaffolded API module cleanly (files, routes, OpenAPI)';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $route = Str::kebab(Str::pluralStudly($name));
        $table = Str::snake(Str::pluralStudly($name));

        if (! $this->option('force') && ! $this->confirm("Delete all scaffold files for [{$name}]?", false)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $namespace = (string) config('api-starter.namespace', 'App');
        $deleted = [];

        $candidates = [
            config('api-starter.paths.model', app_path('Models')) . "/{$name}.php",
            config('api-starter.paths.controller', app_path('Http/Controllers/Api')) . "/{$name}Controller.php",
            config('api-starter.paths.resource', app_path('Http/Resources')) . "/{$name}Resource.php",
            config('api-starter.paths.service', app_path('Services')) . "/{$name}/{$name}Service.php",
            config('api-starter.paths.service', app_path('Services')) . "/{$name}/{$name}ServiceInterface.php",
            config('api-starter.paths.request', app_path('Http/Requests')) . "/{$name}/Store{$name}Request.php",
            config('api-starter.paths.request', app_path('Http/Requests')) . "/{$name}/Update{$name}Request.php",
            config('api-starter.paths.route', base_path('routes/api-starter')) . "/{$route}.php",
            config('api-starter.paths.route_protected', base_path('routes/api-starter-protected')) . "/{$route}.php",
            base_path("routes/api-starter-public/{$route}.php"),
        ];

        foreach ($candidates as $path) {
            if (is_file($path) && File::delete($path)) {
                $deleted[] = $path;
            }
        }

        // Drop legacy fragment if still present
        $legacyOpenApi = config('api-starter.paths.openapi', base_path('storage/api-docs')) . "/{$route}.openapi.json";
        if (is_file($legacyOpenApi) && File::delete($legacyOpenApi)) {
            $deleted[] = $legacyOpenApi;
        }

        // Empty request/service directories
        foreach ([
            config('api-starter.paths.request', app_path('Http/Requests')) . "/{$name}",
            config('api-starter.paths.service', app_path('Services')) . "/{$name}",
        ] as $dir) {
            if (is_dir($dir) && count(File::files($dir)) === 0) {
                File::deleteDirectory($dir);
                $deleted[] = $dir . '/';
            }
        }

        if (! $this->option('keep-migration')) {
            $migrationDir = config('api-starter.paths.migration', database_path('migrations'));
            foreach (File::glob($migrationDir . "/*_create_{$table}_table.php") as $migration) {
                if (File::delete($migration)) {
                    $deleted[] = $migration;
                    $this->warn("Deleted migration {$migration} — run migrate:rollback manually if already applied.");
                }
            }
        }

        $this->removeFromOpenApiIndex($route, $name);

        if ($deleted === []) {
            $this->warn("No scaffold files found for [{$name}].");

            return self::SUCCESS;
        }

        $this->info("Removed [{$name}] (" . count($deleted) . ' paths):');
        foreach ($deleted as $path) {
            $this->line('  - ' . str_replace(base_path() . '/', '', $path));
        }

        $this->newLine();
        $this->comment('If table already migrated: php artisan migrate:rollback');
        $this->comment('Or drop table manually.');

        return self::SUCCESS;
    }

    protected function removeFromOpenApiIndex(string $route, string $modelClass): void
    {
        $indexPath = config('api-starter.paths.openapi', base_path('storage/api-docs')) . '/openapi.json';
        if (! is_file($indexPath)) {
            return;
        }

        $index = json_decode((string) file_get_contents($indexPath), true);
        if (! is_array($index)) {
            return;
        }

        unset($index['paths']["/{$route}"], $index['paths']["/{$route}/{id}"]);

        $index['tags'] = array_values(array_filter(
            $index['tags'] ?? [],
            fn ($tag) => ($tag['name'] ?? null) !== $modelClass
        ));

        foreach (["{$modelClass}", "{$modelClass}Store", "{$modelClass}Update"] as $schema) {
            unset($index['components']['schemas'][$schema]);
        }

        file_put_contents(
            $indexPath,
            json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );

        // Purge legacy *.openapi.json fragments — only openapi.json remains
        $dir = dirname($indexPath);
        foreach (glob($dir . '/*.openapi.json') ?: [] as $file) {
            if (is_file($file)) {
                File::delete($file);
            }
        }

        $this->info('Updated storage/api-docs/openapi.json');
    }
}
