<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Support\AuthConfig;
use Kindharika\ApiStarter\Support\ColumnSchema;

class ApiScaffold extends Command
{
    protected $signature = 'api:scaffold
                            {name : The name of the resource (e.g. Post)}
                            {--columns= : Column spec e.g. name:string,price:decimal:10,2,status:boolean?}
                            {--only= : Comma-separated types (model,controller,service,request,migration,resource,route,openapi)}
                            {--migrate : Run migrations after generation}
                            {--force : Overwrite existing model/resource files}
                            {--auth : Protect routes with Sanctum (auth:sanctum)}
                            {--public : Public routes (no auth), even if auth.enabled}
                            {--audit : Attach Auditable observer trait on model}
                            {--no-route : Skip route generation}
                            {--no-openapi : Skip OpenAPI/Swagger generation}';

    protected $description = 'Scaffold a flat (non-module) API CRUD resource; see module:scaffold for module-based';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));

        try {
            $schema = ColumnSchema::resolve(
                $this->option('columns') !== null ? (string) $this->option('columns') : null,
                $this
            );
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $columnsArg = $this->rebuildColumnsArg($schema);

        $this->line('Columns: ' . implode(', ', $schema->names()));

        $force = $this->option('force') ? ['--force' => true] : [];
        $authFlags = [];
        if ($this->option('auth')) {
            $authFlags['--auth'] = true;
        }
        if ($this->option('public')) {
            $authFlags['--public'] = true;
        }

        $auditFlags = $this->option('audit') ? ['--audit' => true] : [];
        $colFlags = ['--columns' => $columnsArg];

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
            'model' => ['api:make-model', array_merge(['name' => $name], $force, $colFlags, $auditFlags)],
            'migration' => ['api:make-migration', array_merge(['name' => $name], $colFlags)],
            'request' => ['api:make-request', array_merge(['name' => $name], $colFlags)],
            'service' => ['api:make-service', ['name' => $name . 'Service', '--model' => $name]],
            'controller' => ['api:make-controller', array_merge(['name' => $name . 'Controller', '--model' => $name], $colFlags)],
            'resource' => ['api:make-resource', array_merge(['name' => $name], $force, $colFlags)],
            'route' => ['api:make-route', array_merge(['name' => $name], $authFlags)],
            'openapi' => ['api:make-openapi', array_merge(['name' => $name], $openapiFlags, $colFlags)],
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

        $protected = $this->option('public')
            ? false
            : ($this->option('auth') || AuthConfig::enabled());

        $this->newLine();
        $this->info("API resource scaffolding for [{$name}] completed.");
        $this->line("  GET/POST {$resourceUrl}");
        $this->line('Auth: ' . ($protected ? 'ON' : 'OFF') . ' | Audit: ' . ($this->option('audit') ? 'ON' : 'OFF'));
        $this->comment('Tip: --columns=title:string,body:text?,price:decimal:10,2');

        return self::SUCCESS;
    }

    protected function rebuildColumnsArg(ColumnSchema $schema): string
    {
        $parts = [];
        foreach ($schema->columns as $c) {
            $part = $c->name . ':' . $c->type;
            if (in_array($c->type, ['string', 'char'], true) && $c->length) {
                $part .= ':' . $c->length;
            }
            if (in_array($c->type, ['decimal', 'unsignedDecimal', 'float', 'double'], true)) {
                $part .= ':' . ($c->precision ?? '10') . ':' . ($c->scale ?? '2');
            }
            if (in_array($c->type, ['enum', 'set'], true) && ($c->enumValues ?? []) !== []) {
                $part .= ':' . implode(';', $c->enumValues);
            }
            if (in_array($c->type, ['foreignId', 'foreignUuid', 'foreignUlid'], true) && $c->foreignTable) {
                $part .= ':' . $c->foreignTable;
            }
            if ($c->nullable) {
                $part .= '?';
            }
            $parts[] = $part;
        }

        return implode(',', $parts);
    }
}
