<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ApiScaffold extends Command
{
    protected $signature = 'api:scaffold
                            {name : The name of the resource (e.g. Post)}
                            {--only= : Comma-separated types (model,controller,service,request,migration,resource,route,openapi)}
                            {--migrate : Run migrations after generation}
                            {--force : Overwrite existing model/resource files}
                            {--no-route : Skip route generation}
                            {--no-openapi : Skip OpenAPI/Swagger generation}';

    protected $description = 'Scaffold a ready-to-use API CRUD resource (files, routes, OpenAPI)';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $force = $this->option('force') ? ['--force' => true] : [];
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
            'route' => ['api:make-route', ['name' => $name]],
            'openapi' => ['api:make-openapi', ['name' => $name]],
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

        $this->newLine();
        $this->info("API resource scaffolding for [{$name}] completed.");
        $this->line("CRUD endpoints ready at: /{$prefix}/{$route}");
        $this->line('OpenAPI: storage/api-docs/openapi.json (+ per-resource file)');
        $this->comment('Tip: php artisan api:scaffold Post --migrate');

        return self::SUCCESS;
    }
}
