<?php

namespace Kindharika\ApiStarter;

use Illuminate\Support\ServiceProvider;
use Kindharika\ApiStarter\Console\Commands\ApiMakeController;
use Kindharika\ApiStarter\Console\Commands\ApiMakeMigration;
use Kindharika\ApiStarter\Console\Commands\ApiMakeModel;
use Kindharika\ApiStarter\Console\Commands\ApiMakeRequest;
use Kindharika\ApiStarter\Console\Commands\ApiMakeResource;
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
                ApiMakeRequest::class,
                ApiMakeResource::class,
                ApiMakeService::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/api-starter.php' => config_path('api-starter.php'),
            ], 'api-starter-config');

            $this->publishes([
                __DIR__ . '/../stubs' => base_path('stubs/api-starter'),
            ], 'api-starter-stubs');
        }

        // Register datatable macro
        (new DatatableMacro)->register();
    }
}
