<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Audit\AuditManager;
use Kindharika\ApiStarter\Console\Commands\ApiMakeAuth;
use Kindharika\ApiStarter\Console\Commands\ApiMakeAudit;
use Kindharika\ApiStarter\Console\Commands\ApiMakeController;
use Kindharika\ApiStarter\Console\Commands\ApiMakeMigration;
use Kindharika\ApiStarter\Console\Commands\ApiMakeModel;
use Kindharika\ApiStarter\Console\Commands\ApiMakeOpenApi;
use Kindharika\ApiStarter\Console\Commands\ApiMakeRequest;
use Kindharika\ApiStarter\Console\Commands\ApiMakeResource;
use Kindharika\ApiStarter\Console\Commands\ApiMakeRoute;
use Kindharika\ApiStarter\Console\Commands\ApiMakeService;
use Kindharika\ApiStarter\Console\Commands\ApiMakeSso;
use Kindharika\ApiStarter\Console\Commands\ApiOpenApiPrune;
use Kindharika\ApiStarter\Console\Commands\ApiRemove;
use Kindharika\ApiStarter\Console\Commands\ApiScaffold;
use Kindharika\ApiStarter\Console\Commands\ModuleList;
use Kindharika\ApiStarter\Console\Commands\ModuleMake;
use Kindharika\ApiStarter\Console\Commands\ModuleRemove;
use Kindharika\ApiStarter\Console\Commands\ModuleScaffold;
use Kindharika\ApiStarter\Http\Middleware\EnsurePermission;
use Kindharika\ApiStarter\Http\Middleware\EnsureRole;
use Kindharika\ApiStarter\Macros\DatatableMacro;
use Kindharika\ApiStarter\Modules\ModuleManifest;
use Kindharika\ApiStarter\Modules\ModulePaths;
use Kindharika\ApiStarter\Rbac\RbacManager;
use Kindharika\ApiStarter\Support\AuthConfig;

class ApiStarterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/api-starter.php',
            'api-starter'
        );

        $this->app->singleton(RbacManager::class);
        $this->app->singleton(AuditManager::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Existing api:* — unchanged
                ApiScaffold::class,
                ApiRemove::class,
                ApiMakeAuth::class,
                ApiMakeAudit::class,
                ApiMakeSso::class,
                ApiMakeController::class,
                ApiMakeMigration::class,
                ApiMakeModel::class,
                ApiMakeOpenApi::class,
                ApiMakeRequest::class,
                ApiMakeResource::class,
                ApiMakeRoute::class,
                ApiMakeService::class,
                ApiOpenApiPrune::class,
                // Modular module:* — separate namespace
                ModuleMake::class,
                ModuleScaffold::class,
                ModuleRemove::class,
                ModuleList::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/api-starter.php' => config_path('api-starter.php'),
            ], 'api-starter-config');

            $this->publishes([
                __DIR__ . '/../stubs' => base_path('stubs/api-starter'),
            ], 'api-starter-stubs');
        }

        $this->registerMiddlewareAliases();
        (new DatatableMacro)->register();
        $this->loadScaffoldedRoutes();
        $this->loadModuleRoutes();
        $this->registerOpenApiRoutes();
    }

    protected function registerMiddlewareAliases(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('api-starter.permission', EnsurePermission::class);
        $router->aliasMiddleware('api-starter.role', EnsureRole::class);
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
     * Auto-load module routes. Does not affect existing api-starter routes.
     */
    protected function loadModuleRoutes(): void
    {
        if (! config('api-starter.modules.enabled', true)) {
            return;
        }

        $rootPrefix = trim((string) config('api-starter.route_prefix', 'api'), '/');

        foreach (ModulePaths::list() as $module) {
            $manifest = ModuleManifest::load($module);

            if ($manifest !== null && ! $manifest->enabled) {
                continue;
            }

            $moduleDir = ModulePaths::module($module);
            $modulePrefix = $rootPrefix . '/' . ModulePaths::prefix($module);

            $publicFile = $moduleDir . '/Routes/api.php';
            if (is_file($publicFile)) {
                // Auth lives inside the file (Route::middleware(['auth:sanctum'])->...)
                Route::middleware(AuthConfig::publicMiddleware())
                    ->prefix($modulePrefix)
                    ->group($publicFile);
            }

            // BC: legacy second file still wraps auth at loader level
            $protectedFile = $moduleDir . '/Routes/api-protected.php';
            if (is_file($protectedFile)) {
                $middleware = AuthConfig::protectedMiddleware();

                if ($manifest !== null && config('api-starter.rbac.enabled', false)) {
                    foreach ($manifest->permissions as $permission) {
                        $middleware[] = 'api-starter.permission:' . $permission;
                    }
                    foreach ($manifest->roles as $role) {
                        $middleware[] = 'api-starter.role:' . $role;
                    }
                }

                Route::middleware(array_values(array_unique($middleware)))
                    ->prefix($modulePrefix)
                    ->group($protectedFile);
            }
        }
    }

    /**
     * Paths under /{modulePrefix} that need Bearer in OpenAPI.
     *
     * @return list<string>
     */
    protected function discoverProtectedModulePaths(
        string $modulePrefix,
        string $module,
        string $contents,
        bool $legacyProtectedFile,
    ): array {
        $out = [];
        $authNeedle = AuthConfig::middleware()[0] ?? 'auth:sanctum';

        if (preg_match_all(
            '/\/\/ api-starter:resource:([A-Za-z0-9_]+):begin(.*?)\/\/ api-starter:resource:\1:end/s',
            $contents,
            $blocks,
            PREG_SET_ORDER
        )) {
            foreach ($blocks as $block) {
                $resourceName = $block[1];
                $body = $block[2];
                $needsAuth = $legacyProtectedFile
                    || str_contains($body, $authNeedle)
                    || str_contains($body, 'auth:sanctum');

                if (! $needsAuth) {
                    continue;
                }

                if (preg_match("/apiResource\\('([^']+)'/", $body, $m)) {
                    $out[] = $modulePrefix . '/' . $m[1];
                } elseif (str_contains($body, "Route::get('/',")
                    || str_contains($body, 'Route::get("/",')) {
                    if (Str::studly($resourceName) === Str::studly($module)) {
                        $out[] = $modulePrefix;
                    }
                }
            }

            return $out;
        }

        // Unmarked / legacy file: whole file protected, or lines with auth middleware
        if ($legacyProtectedFile) {
            if (preg_match_all("/apiResource\\('([^']+)'/", $contents, $matches)) {
                foreach ($matches[1] as $resource) {
                    $out[] = $modulePrefix . '/' . $resource;
                }
            }
            if (str_contains($contents, "Route::get('/',") || str_contains($contents, 'Route::get("/",')) {
                $out[] = $modulePrefix;
            }

            return $out;
        }

        if (preg_match_all(
            "/Route::middleware\\(\\[[^\\]]*'" . preg_quote($authNeedle, '/') . "'[^\\]]*\\]\\)->apiResource\\('([^']+)'/",
            $contents,
            $matches
        )) {
            foreach ($matches[1] as $resource) {
                $out[] = $modulePrefix . '/' . $resource;
            }
        }

        return $out;
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
                        'description' => 'Paste token ONLY — no "Bearer " prefix. Example: 1|xxxxx',
                    ],
                ]
            );

            // Attach security to paths that live under routes/api-starter-protected
            // so Swagger UI actually sends Authorization header (fixes 401).
            $protectedDir = config('api-starter.paths.route_protected', base_path('routes/api-starter-protected'));
            $protectedRoutes = [];
            if (is_dir($protectedDir)) {
                foreach (glob($protectedDir . '/*.php') ?: [] as $file) {
                    $protectedRoutes[] = basename($file, '.php');
                }
            }

            // Module routes that require Bearer (api.php with auth middleware, or legacy api-protected.php)
            $moduleProtected = [];
            if (config('api-starter.modules.enabled', true)) {
                foreach (ModulePaths::list() as $module) {
                    $prefix = ModulePaths::prefix($module);
                    foreach (['api.php', 'api-protected.php'] as $routeName) {
                        $file = ModulePaths::module($module) . '/Routes/' . $routeName;
                        if (! is_file($file)) {
                            continue;
                        }
                        $contents = (string) file_get_contents($file);
                        $legacyFile = $routeName === 'api-protected.php';
                        $moduleProtected = array_merge(
                            $moduleProtected,
                            $this->discoverProtectedModulePaths($prefix, $module, $contents, $legacyFile)
                        );
                    }
                }
                $moduleProtected = array_values(array_unique($moduleProtected));
            }

            foreach ($spec['paths'] ?? [] as $pathKey => $operations) {
                if (! is_array($operations)) {
                    continue;
                }

                $segment = ltrim(explode('/', trim($pathKey, '/'))[0] ?? '', '/');
                $trimmed = ltrim($pathKey, '/');
                $needsAuth = in_array($segment, $protectedRoutes, true)
                    || str_starts_with($pathKey, '/auth/logout')
                    || str_starts_with($pathKey, '/auth/me');

                if (! $needsAuth) {
                    foreach ($moduleProtected as $mp) {
                        if ($trimmed === $mp || str_starts_with($trimmed, $mp . '/')) {
                            $needsAuth = true;
                            break;
                        }
                    }
                }

                if (! $needsAuth) {
                    continue;
                }

                foreach ($operations as $method => $operation) {
                    if (! is_array($operation) || in_array($method, ['parameters', 'summary', 'description', 'servers'], true)) {
                        continue;
                    }
                    $spec['paths'][$pathKey][$method]['security'] = [['sanctum' => []]];
                }
            }

            if (AuthConfig::enabled()) {
                $spec['security'] = [['sanctum' => []]];
            }

            return response()->json($spec, 200, [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        })->name('api-starter.openapi.json');

        Route::get($docsUi, function () {
            $title = e((string) config('api-starter.openapi.title', 'API Documentation'));
            $specPath = '/' . trim((string) config('api-starter.openapi.docs_json', '/api/docs/openapi.json'), '/');
            $specUrl = e($specPath);

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
      persistAuthorization: true,
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
