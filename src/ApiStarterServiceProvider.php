<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter;

use Illuminate\Support\Facades\File;
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
        $this->registerOpenApiRoutes();
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

    protected function registerOpenApiRoutes(): void
    {
        if (! config('api-starter.openapi.enabled', true)) {
            return;
        }

        $docsJson = trim((string) config('api-starter.openapi.docs_json', '/api/docs/openapi.json'), '/');
        $docsUi = trim((string) config('api-starter.openapi.docs_ui', '/api/docs'), '/');

        Route::get($docsJson, function () {
            $path = config('api-starter.paths.openapi', base_path('storage/api-docs')) . '/openapi.json';

            if (! File::exists($path)) {
                return response()->json([
                    'message' => 'OpenAPI spec not found. Run: php artisan api:scaffold {Resource}',
                ], 404);
            }

            return response()->file($path, [
                'Content-Type' => 'application/json',
            ]);
        })->name('api-starter.openapi.json');

        Route::get($docsUi, function () {
            $title = e((string) config('api-starter.openapi.title', 'API Documentation'));
            $specUrl = e(url(trim((string) config('api-starter.openapi.docs_json', '/api/docs/openapi.json'), '/')));

            $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$title}</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
  <style>body{margin:0} .topbar{display:none}</style>
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
  <script>
    window.ui = SwaggerUIBundle({
      url: "{$specUrl}",
      dom_id: "#swagger-ui",
      deepLinking: true,
      presets: [SwaggerUIBundle.presets.apis],
      layout: "BaseLayout"
    });
  </script>
</body>
</html>
HTML;

            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        })->name('api-starter.openapi.ui');
    }
}
