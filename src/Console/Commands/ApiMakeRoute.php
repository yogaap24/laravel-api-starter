<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Console\InteractsWithStubs;
use Kindharika\ApiStarter\Support\AuthConfig;

class ApiMakeRoute extends Command
{
    use InteractsWithStubs;

    protected $signature = 'api:make-route
                            {name : The resource name}
                            {--auth : Place route under Sanctum-protected directory}
                            {--public : Place route under public (no auth) directory}';

    protected $description = 'Create an apiResource route file auto-loaded by the package';

    public function handle(): int
    {
        $modelClass = Str::studly($this->argument('name'));
        $controller = $modelClass . 'Controller';
        $route = Str::kebab(Str::pluralStudly($modelClass));

        $protected = $this->resolveProtected();
        $dir = $protected
            ? config('api-starter.paths.route', base_path('routes/api-starter'))
            : config('api-starter.paths.route_public', base_path('routes/api-starter-public'));

        $path = $dir . '/' . $route . '.php';

        // Remove from the other directory if switching auth mode.
        $otherDir = $protected
            ? config('api-starter.paths.route_public', base_path('routes/api-starter-public'))
            : config('api-starter.paths.route', base_path('routes/api-starter'));
        $otherPath = $otherDir . '/' . $route . '.php';
        if (is_file($otherPath)) {
            unlink($otherPath);
        }

        $this->writeStub('route.stub', $path, [
            'namespace' => $this->rootNamespace(),
            'controller' => $controller,
            'route' => $route,
        ]);

        $mode = $protected ? 'protected (auth:' . config('api-starter.auth.guard', 'sanctum') . ')' : 'public';
        $this->info("API route [{$route}] → {$path} [{$mode}]");

        if ($protected && ! class_exists(\Laravel\Sanctum\Sanctum::class)) {
            $this->warn('laravel/sanctum not installed. Run: composer require laravel/sanctum');
        }

        return self::SUCCESS;
    }

    protected function resolveProtected(): bool
    {
        if ($this->option('public')) {
            return false;
        }

        if ($this->option('auth')) {
            return true;
        }

        return AuthConfig::enabled();
    }
}
