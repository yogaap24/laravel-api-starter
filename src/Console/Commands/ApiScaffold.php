<?php

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ApiScaffold extends Command
{
    protected $signature = 'api:scaffold
                            {name : The name of the resource (e.g. Post)}
                            {--only= : Comma-separated list of types to generate (model,controller,service,request,migration,resource)}
                            {--migrate : Run migrations after generation}';

    protected $description = 'Scaffold all API files for a resource (model, migration, request, service, controller, resource)';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $only = collect($this->option('only') ? explode(',', $this->option('only')) : []);

        $map = [
            'model'      => ['api:make-model', [$name]],
            'migration'  => ['api:make-migration', [$name]],
            'request'    => ['api:make-request', [$name]],
            'service'    => ['api:make-service', [$name . 'Service', '--model' => $name]],
            'controller' => ['api:make-controller', [$name . 'Controller', '--model' => $name]],
            'resource'   => ['api:make-resource', [$name]],
        ];

        foreach ($map as $type => $args) {
            if ($only->isNotEmpty() && !in_array($type, $only->toArray())) {
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

        $this->info("API resource scaffolding for [{$name}] completed.");

        return self::SUCCESS;
    }
}
