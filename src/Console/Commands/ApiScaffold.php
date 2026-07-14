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
                            {--no-route : Skip route generation}
                            {--no-openapi : Skip OpenAPI/Swagger generation}';

    protected $description = 'Scaffold a ready-to-use API CRUD resource (files, routes, OpenAPI)';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $only = collect($this->option('only') ? explode(',', (string) $this->option('only')) : [])
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values();

        $map = [
            'model' => ['api:make-model', [$name]],
            'migration' => ['api:make-migration', [$name]],
            'request' => ['api:make-request', [$name]],
            'service' => ['api:make-service', [$name . 'Service', '--model' => $name]],
            'controller' => ['api:make-controller', [$name . 'Controller', '--model' => $name]],
            'resource' => ['api:make-resource', [$name]],
            'route' => ['api:make-route', [$name]],
            'openapi' => ['api:make-openapi', [$name]],
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
            $this->call($command, $commandArgs);
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
