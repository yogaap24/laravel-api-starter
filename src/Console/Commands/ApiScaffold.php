<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Support\AuthConfig;

class ApiScaffold extends Command
{
    protected $signature = 'api:scaffold
                            {name : The name of the resource (e.g. Post)}
                            {--only= : Comma-separated types (model,controller,service,request,migration,resource,route,openapi)}
                            {--migrate : Run migrations after generation}
                            {--force : Overwrite existing model/resource files}
                            {--auth : Protect routes with Sanctum (auth:sanctum)}
                            {--public : Public routes (no auth), even if auth.enabled}
                            {--no-route : Skip route generation}
                            {--no-openapi : Skip OpenAPI/Swagger generation}';

    protected $description = 'Scaffold a ready-to-use API CRUD resource (files, routes, OpenAPI)';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $force = $this->option('force') ? ['--force' => true] : [];
        $authFlags = [];
        if ($this->option('auth')) {
            $authFlags['--auth'] = true;
        }
        if ($this->option('public')) {
            $authFlags['--public'] = true;
        }

        $openapiFlags = [];
        $willProtect = $this->option('auth') || (AuthConfig::enabled() && ! $this->option('public'));
        if ($willProtect) {
            $openapiFlags['--auth'] = true;
        }

        $only = collect($this->option('only') ? explode(',', (string) $this->option('only')) : [])
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values();

        $map = [
            'model' => ['api:make-model', array_merge(['name' => $name], $force)],
            'migration' => ['api:make-migration', ['name' => $name]],
            'request' => ['api:make-request', ['name' => $name]],
            'service' => ['api:make-service', ['name' => $name . 'Service', '--model' => $name]],
            'controller' => ['api:make-controller', ['name' => $name . 'Controller', '--model' => $name]],
            'resource' => ['api:make-resource', array_merge(['name' => $name], $force)],
            'route' => ['api:make-route', array_merge(['name' => $name], $authFlags)],
            'openapi' => ['api:make-openapi', array_merge(['name' => $name], $openapiFlags)],
        ];

        if ($this->option('no-route')) {
            unset($map['route']);
        }

        if ($this->option('no-openapi')) {
            unset($map['openapi']);
        }

        foreach ($map as $type => $args) {
            if ($only->isNotEmpty() && ! $only->contains($type)) {
                continue;
            }

            [$command, $commandArgs] = $args;

            $this->info("Generating {$type}...");

            $exitCode = $this->call($command, $commandArgs);

            if ($exitCode !== self::SUCCESS) {
                $this->error("Failed generating {$type}.");

                return $exitCode;
            }
        }

        if ($this->option('migrate')) {
            $this->info('Running migrations...');
            $this->call('migrate');
        }

        $route = Str::kebab(Str::pluralStudly($name));
        $prefix = trim((string) config('api-starter.route_prefix', 'api'), '/');
        $baseUrl = rtrim((string) config('app.url', 'http://localhost'), '/');
        $resourceUrl = "{$baseUrl}/{$prefix}/{$route}";
        $docsUi = $baseUrl . '/' . trim((string) config('api-starter.openapi.docs_ui', '/api/docs'), '/');
        $docsJson = $baseUrl . '/' . trim((string) config('api-starter.openapi.docs_json', '/api/docs/openapi.json'), '/');

        $protected = $this->option('public')
            ? false
            : ($this->option('auth') || AuthConfig::enabled());

        $this->newLine();
        $this->info("API resource scaffolding for [{$name}] completed.");
        $this->newLine();
        $this->line('CRUD endpoints:');
        $this->line("  GET    {$resourceUrl}");
        $this->line("  POST   {$resourceUrl}");
        $this->line("  GET    {$resourceUrl}/{id}");
        $this->line("  PUT    {$resourceUrl}/{id}");
        $this->line("  DELETE {$resourceUrl}/{id}");
        $this->line('Auth: ' . ($protected
            ? 'ON → routes/api-starter-protected (auth:' . config('api-starter.auth.guard', 'sanctum') . ')'
            : 'OFF → routes/api-starter (public)'));

        if (! $this->option('no-openapi') && config('api-starter.openapi.enabled', true)) {
            $this->newLine();
            $this->line('Swagger / OpenAPI:');
            $this->line("  UI:   {$docsUi}");
            $this->line("  JSON: {$docsJson}");
            $this->line('  File: storage/api-docs/openapi.json');
        }

        if ($protected && ! class_exists(\Laravel\Sanctum\Sanctum::class)) {
            $this->newLine();
            $this->warn('Auth enabled but laravel/sanctum missing:');
            $this->comment('  composer require laravel/sanctum');
        }

        $this->newLine();
        $this->comment('Auth endpoints: php artisan api:make-auth');
        $this->comment('Tip: php artisan api:scaffold Post --auth --migrate');

        return self::SUCCESS;
    }
}
