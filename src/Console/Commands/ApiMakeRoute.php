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
                            {--auth : Place route under routes/api-starter-protected (Sanctum)}
                            {--public : Place route under routes/api-starter (public)}';

    protected $description = 'Create an apiResource route file auto-loaded by the package';

    public function handle(): int
    {
        $modelClass = Str::studly($this->argument('name'));
        $controller = $modelClass . 'Controller';
        $route = Str::kebab(Str::pluralStudly($modelClass));

        $protected = $this->resolveProtected();
        $publicDir = config('api-starter.paths.route', base_path('routes/api-starter'));
        $protectedDir = config('api-starter.paths.route_protected', base_path('routes/api-starter-protected'));

        $dir = $protected ? $protectedDir : $publicDir;
        $path = $dir . '/' . $route . '.php';

        // Remove from the other directory if switching auth mode.
        $otherPath = ($protected ? $publicDir : $protectedDir) . '/' . $route . '.php';
        if (is_file($otherPath)) {
            unlink($otherPath);
        }

        // Also clean legacy public folder.
        $legacy = base_path('routes/api-starter-public/' . $route . '.php');
        if (is_file($legacy)) {
            unlink($legacy);
        }

        $this->writeStub('route.stub', $path, [
            'namespace' => $this->rootNamespace(),
            'controller' => $controller,
            'route' => $route,
        ]);

        $mode = $protected
            ? 'protected → routes/api-starter-protected (auth:' . config('api-starter.auth.guard', 'sanctum') . ')'
            : 'public → routes/api-starter';
        $this->info("API route [{$route}] created [{$mode}]");

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

        // Only NEW scaffolds follow API_STARTER_AUTH — never flip existing public folder.
        return AuthConfig::enabled();
    }
}
