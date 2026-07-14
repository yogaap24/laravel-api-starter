<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Console\InteractsWithStubs;

class ApiMakeRoute extends Command
{
    use InteractsWithStubs;

    protected $signature = 'api:make-route {name}';

    protected $description = 'Create an apiResource route file auto-loaded by the package';

    public function handle(): int
    {
        $modelClass = Str::studly($this->argument('name'));
        $controller = $modelClass . 'Controller';
        $route = Str::kebab(Str::pluralStudly($modelClass));
        $dir = config('api-starter.paths.route', base_path('routes/api-starter'));
        $path = $dir . '/' . $route . '.php';

        $this->writeStub('route.stub', $path, [
            'namespace' => $this->rootNamespace(),
            'controller' => $controller,
            'route' => $route,
        ]);

        $this->info("API route [{$route}] registered at routes/api-starter/{$route}.php");

        return self::SUCCESS;
    }
}
