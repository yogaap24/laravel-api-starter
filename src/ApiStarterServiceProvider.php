<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Kindharika\ApiStarter\Console\Commands\ApiMakeController;
use Kindharika\ApiStarter\Console\Commands\ApiMakeMigration;
use Kindharika\ApiStarter\Console\Commands\ApiMakeModel;
use Kindharika\ApiStarter\Console\Commands\ApiMakeOpenApi;
use Kindharika\ApiStarter\Console\Commands\ApiMakeRequest;
use Kindharika\ApiStarter\Console\Commands\ApiMakeResource;
use Kindharika\ApiStarter\Console\Commands\ApiMakeRoute;
use Kindharika\ApiStarter\Console\Commands\ApiMakeService;
use Kindharika\ApiStarter\Console\Commands\ApiScaffold;
use Kindharika\ApiStarter\Macros\DatatableMacro;

class ApiStarterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/api-starter.php',
            'api-starter'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ApiScaffold::class,
                ApiMakeController::class,
                ApiMakeMigration::class,
                ApiMakeModel::class,
                ApiMakeOpenApi::class,
                ApiMakeRequest::class,
                ApiMakeResource::class,
                ApiMakeRoute::class,
                ApiMakeService::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/api-starter.php' => config_path('api-starter.php'),
            ], 'api-starter-config');

            $this->publishes([
                __DIR__ . '/../stubs' => base_path('stubs/api-starter'),
            ], 'api-starter-stubs');
        }

        (new DatatableMacro)->register();
        $this->loadScaffoldedRoutes();
    }

    protected function loadScaffoldedRoutes(): void
    {
        $dir = config('api-starter.paths.route', base_path('routes/api-starter'));

        if (! is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*.php') ?: [];

        foreach ($files as $file) {
            Route::middleware(config('api-starter.route_middleware', ['api']))
                ->prefix((string) config('api-starter.route_prefix', 'api'))
                ->group($file);
        }
    }
}
