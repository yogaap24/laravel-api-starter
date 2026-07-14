<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Kindharika\ApiStarter\Console\Commands\ApiMakeAuth;
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
use Kindharika\ApiStarter\Support\AuthConfig;

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
                ApiMakeAuth::class,
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
        $prefix = (string) config('api-starter.route_prefix', 'api');

        // Default folder = PUBLIC (keeps existing scaffolds unauthenticated)
        $publicDir = config('api-starter.paths.route', base_path('routes/api-starter'));
        // --auth only
        $protectedDir = config('api-starter.paths.route_protected', base_path('routes/api-starter-protected'));

        // BC: old mistaken public folder name
        $legacyPublic = base_path('routes/api-starter-public');

        $this->loadRouteDirectory($publicDir, $prefix, AuthConfig::publicMiddleware());
        $this->loadRouteDirectory($legacyPublic, $prefix, AuthConfig::publicMiddleware());
        $this->loadRouteDirectory($protectedDir, $prefix, AuthConfig::protectedMiddleware());
    }

    /**
     * @param  list<string>  $middleware
     */
    protected function loadRouteDirectory(string $dir, string $prefix, array $middleware): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*.php') ?: [];

        foreach ($files as $file) {
            Route::middleware($middleware)
                ->prefix($prefix)
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

        // Docs stay public — no auth middleware.
        Route::get($docsJson, function () {
            $path = config('api-starter.paths.openapi', base_path('storage/api-docs')) . '/openapi.json';

            if (! File::exists($path)) {
                return response()->json([
                    'message' => 'OpenAPI spec not found. Run: php artisan api:scaffold {Resource}',
                ], 404);
            }

            $spec = json_decode((string) File::get($path), true);

            if (! is_array($spec)) {
                return response()->json(['message' => 'Invalid OpenAPI JSON'], 500);
            }

            $serverUrl = (string) config('api-starter.openapi.server_url', '/api');
            $spec['servers'] = [
                [
                    'url' => $serverUrl,
                    'description' => 'Same origin as docs',
                ],
            ];

            // Always expose Bearer scheme (for --auth resources). Required when auth.enabled.
            $spec['components'] ??= [];
            $spec['components']['securitySchemes'] = array_merge(
                $spec['components']['securitySchemes'] ?? [],
                [
                    'sanctum' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'Token',
                        'description' => 'Laravel Sanctum personal access token. Header: Authorization: Bearer {token}',
                    ],
                ]
            );

            if (AuthConfig::enabled()) {
                $spec['security'] = [['sanctum' => []]];
            }

            return response()->json($spec, 200, [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        })->name('api-starter.openapi.json');

        Route::get($docsUi, function () {
            $title = e((string) config('api-starter.openapi.title', 'API Documentation'));
            $specPath = '/' . trim((string) config('api-starter.openapi.docs_json', '/api/docs/openapi.json'), '/');
            $specUrl = e($specPath);
            $persistAuth = AuthConfig::enabled() ? 'true' : 'false';

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
      persistAuthorization: {$persistAuth},
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
