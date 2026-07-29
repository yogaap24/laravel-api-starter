<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Console\InteractsWithStubs;
use Kindharika\ApiStarter\Modules\ModulePaths;
use Kindharika\ApiStarter\Support\AuthConfig;

class ModuleScaffold extends Command
{
    use InteractsWithStubs;

    protected $signature = 'module:scaffold
                            {module : Module name (e.g. Blog)}
                            {name : Resource name (e.g. Post)}
                            {--migrate : Run migrations after generation}
                            {--force : Overwrite existing files}
                            {--auth : Protect resource with Sanctum}
                            {--public : Force public routes}
                            {--permission= : RBAC permission middleware (e.g. posts.manage)}
                            {--role= : RBAC role middleware (e.g. admin)}
                            {--no-openapi : Skip OpenAPI generation}';

    protected $description = 'Scaffold CRUD resource inside an API module (module:* — not api:*)';

    public function handle(): int
    {
        if (! config('api-starter.modules.enabled', true)) {
            $this->error('Modules disabled in config.');

            return self::FAILURE;
        }

        $module = Str::studly($this->argument('module'));
        $name = Str::studly($this->argument('name'));

        if (! ModulePaths::exists($module)) {
            $this->warn("Module [{$module}] missing — creating skeleton…");
            $exit = $this->call('module:make', ['name' => $module]);
            if ($exit !== self::SUCCESS) {
                return $exit;
            }
        }

        $moduleNs = ModulePaths::namespace($module);
        $moduleDir = ModulePaths::module($module);
        $force = (bool) $this->option('force');
        $protected = $this->resolveProtected();
        $table = Str::snake(Str::pluralStudly($name));
        $route = Str::kebab(Str::pluralStudly($name));
        $controller = $name . 'Controller';

        $files = [
            [
                'stub' => 'module/model.stub',
                'path' => "{$moduleDir}/Models/{$name}.php",
                'vars' => [
                    'namespace' => $moduleNs . '\\Models',
                    'class' => $name,
                    'table' => $table,
                ],
            ],
            [
                'stub' => 'module/controller.stub',
                'path' => "{$moduleDir}/Http/Controllers/{$controller}.php",
                'vars' => [
                    'namespace' => $moduleNs,
                    'class' => $controller,
                    'modelClass' => $name,
                    'route' => $route,
                    'module' => $module,
                ],
            ],
            [
                'stub' => 'module/service.stub',
                'path' => "{$moduleDir}/Services/{$name}/{$name}Service.php",
                'vars' => [
                    'namespace' => $moduleNs,
                    'class' => $name . 'Service',
                    'modelClass' => $name,
                ],
            ],
            [
                'stub' => 'module/service-interface.stub',
                'path' => "{$moduleDir}/Services/{$name}/{$name}ServiceInterface.php",
                'vars' => [
                    'namespace' => $moduleNs,
                    'class' => $name . 'ServiceInterface',
                    'modelClass' => $name,
                ],
            ],
            [
                'stub' => 'module/request.store.stub',
                'path' => "{$moduleDir}/Http/Requests/{$name}/Store{$name}Request.php",
                'vars' => [
                    'namespace' => $moduleNs,
                    'class' => "Store{$name}Request",
                    'modelClass' => $name,
                ],
            ],
            [
                'stub' => 'module/request.update.stub',
                'path' => "{$moduleDir}/Http/Requests/{$name}/Update{$name}Request.php",
                'vars' => [
                    'namespace' => $moduleNs,
                    'class' => "Update{$name}Request",
                    'modelClass' => $name,
                ],
            ],
            [
                'stub' => 'module/resource.stub',
                'path' => "{$moduleDir}/Http/Resources/{$name}Resource.php",
                'vars' => [
                    'namespace' => $moduleNs . '\\Http\\Resources',
                    'class' => $name . 'Resource',
                ],
            ],
        ];

        foreach ($files as $file) {
            if (is_file($file['path']) && ! $force) {
                $this->warn("Skip (exists): {$file['path']}");
                continue;
            }
            $this->writeStub($file['stub'], $file['path'], $file['vars']);
            $this->info("Created: {$file['path']}");
        }

        $this->writeMigration($moduleDir, $table, $name, $force);
        $this->appendRoute($moduleDir, $moduleNs, $controller, $route, $protected);
        $this->writeOpenApi($module, $name, $route, $protected);

        if ($this->option('migrate')) {
            $this->call('migrate', [
                '--path' => 'app/Modules/' . $module . '/Database/Migrations',
            ]);
        }

        $prefix = trim((string) config('api-starter.route_prefix', 'api'), '/');
        $modulePrefix = ModulePaths::prefix($module);
        $baseUrl = rtrim((string) config('app.url', 'http://localhost'), '/');
        $resourceUrl = "{$baseUrl}/{$prefix}/{$modulePrefix}/{$route}";

        $this->newLine();
        $this->info("Module scaffold [{$module}/{$name}] done.");
        $this->line('CRUD:');
        $this->line("  GET    {$resourceUrl}");
        $this->line("  POST   {$resourceUrl}");
        $this->line("  GET    {$resourceUrl}/{id}");
        $this->line("  PUT    {$resourceUrl}/{id}");
        $this->line("  DELETE {$resourceUrl}/{id}");
        $this->line('Auth: ' . ($protected ? 'ON (api-protected.php)' : 'OFF (api.php)'));

        if ($this->option('permission') || $this->option('role')) {
            $this->line('RBAC: permission=' . ($this->option('permission') ?: '-')
                . ' role=' . ($this->option('role') ?: '-'));
            if (! config('api-starter.rbac.enabled', false)) {
                $this->warn('RBAC middleware attached but api-starter.rbac.enabled=false (no-op until enabled).');
            }
        }

        return self::SUCCESS;
    }

    protected function resolveProtected(): bool
    {
        if ($this->option('public')) {
            return false;
        }

        if ($this->option('auth') || $this->option('permission') || $this->option('role')) {
            return true;
        }

        return AuthConfig::enabled();
    }

    protected function writeMigration(string $moduleDir, string $table, string $name, bool $force): void
    {
        $dir = $moduleDir . '/Database/Migrations';
        $this->ensureDirectoryExists($dir);

        $existing = glob($dir . "/*_create_{$table}_table.php") ?: [];
        if ($existing !== [] && ! $force) {
            $this->warn("Skip migration (exists): {$existing[0]}");

            return;
        }

        if ($existing !== [] && $force) {
            foreach ($existing as $file) {
                unlink($file);
            }
        }

        $filename = date('Y_m_d_His') . "_create_{$table}_table.php";
        $this->writeStub('module/migration.stub', $dir . '/' . $filename, [
            'table' => $table,
        ]);
        $this->info("Created: {$dir}/{$filename}");
    }

    protected function appendRoute(
        string $moduleDir,
        string $moduleNs,
        string $controller,
        string $route,
        bool $protected,
    ): void {
        $file = $protected
            ? $moduleDir . '/Routes/api-protected.php'
            : $moduleDir . '/Routes/api.php';

        $other = $protected
            ? $moduleDir . '/Routes/api.php'
            : $moduleDir . '/Routes/api-protected.php';

        // Remove from the other file if switching.
        if (is_file($other)) {
            $this->removeRouteLine($other, $route);
        }

        $controllerFqcn = $moduleNs . '\\Http\\Controllers\\' . $controller;
        $middlewareParts = [];

        if ($permission = $this->option('permission')) {
            $middlewareParts[] = "api-starter.permission:{$permission}";
        }
        if ($role = $this->option('role')) {
            $middlewareParts[] = "api-starter.role:{$role}";
        }

        if ($middlewareParts !== []) {
            $mw = implode("','", $middlewareParts);
            $line = "Route::middleware(['{$mw}'])->apiResource('{$route}', \\{$controllerFqcn}::class);";
        } else {
            $line = "Route::apiResource('{$route}', \\{$controllerFqcn}::class);";
        }

        $contents = is_file($file) ? (string) file_get_contents($file) : "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n";

        if (! str_contains($contents, 'use Illuminate\\Support\\Facades\\Route;')) {
            $contents = preg_replace('/^<\?php\s*/', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n", $contents, 1) ?? $contents;
        }

        // Replace existing resource line for same route.
        $pattern = '/^Route::(?:middleware\([^)]+\)->)?apiResource\(\'' . preg_quote($route, '/') . '\'.*$/m';
        if (preg_match($pattern, $contents)) {
            $contents = preg_replace($pattern, $line, $contents) ?? $contents;
        } else {
            $contents = rtrim($contents) . "\n" . $line . "\n";
        }

        file_put_contents($file, $contents);
        $this->info('Updated routes: ' . $file);
    }

    protected function removeRouteLine(string $file, string $route): void
    {
        $contents = (string) file_get_contents($file);
        $pattern = '/^Route::(?:middleware\([^)]+\)->)?apiResource\(\'' . preg_quote($route, '/') . '\'.*\n?/m';
        $updated = preg_replace($pattern, '', $contents);
        if (is_string($updated) && $updated !== $contents) {
            file_put_contents($file, $updated);
        }
    }

    protected function writeOpenApi(string $module, string $name, string $route, bool $protected): void
    {
        if ($this->option('no-openapi') || ! config('api-starter.openapi.enabled', true)) {
            return;
        }

        $modulePrefix = ModulePaths::prefix($module);
        $pathKey = $modulePrefix . '/' . $route;
        $dir = config('api-starter.paths.openapi', base_path('storage/api-docs'));
        $path = $dir . '/module-' . $modulePrefix . '-' . $route . '.openapi.json';
        $tag = $module . '/' . $name;

        $this->writeStub('module/openapi.stub.json', $path, [
            'title' => (string) config('api-starter.openapi.title', 'API Documentation'),
            'version' => (string) config('api-starter.openapi.version', '1.0.0'),
            'serverUrl' => (string) config('api-starter.openapi.server_url', '/api'),
            'modelClass' => $name,
            'module' => $module,
            'tag' => $tag,
            'route' => $pathKey,
        ]);

        if ($protected) {
            $spec = json_decode((string) file_get_contents($path), true);
            if (is_array($spec)) {
                $spec['components']['securitySchemes']['sanctum'] = [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'Token',
                    'description' => 'Paste token ONLY (no "Bearer " prefix).',
                ];
                foreach ($spec['paths'] ?? [] as $p => $ops) {
                    if (! is_array($ops)) {
                        continue;
                    }
                    foreach ($ops as $method => $op) {
                        if (! is_array($op) || in_array($method, ['parameters', 'summary', 'description', 'servers'], true)) {
                            continue;
                        }
                        $spec['paths'][$p][$method]['security'] = [['sanctum' => []]];
                    }
                }
                file_put_contents($path, json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
            }
        }

        $this->mergeOpenApi($dir, $path, $tag);
        $this->info("OpenAPI: {$path}");
    }

    protected function mergeOpenApi(string $dir, string $resourcePath, string $tag): void
    {
        $resource = json_decode((string) file_get_contents($resourcePath), true);
        if (! is_array($resource)) {
            return;
        }

        $indexPath = $dir . '/openapi.json';
        $index = is_file($indexPath)
            ? json_decode((string) file_get_contents($indexPath), true)
            : null;

        if (! is_array($index)) {
            $index = [
                'openapi' => '3.0.3',
                'info' => [
                    'title' => config('api-starter.openapi.title', 'API Documentation'),
                    'version' => config('api-starter.openapi.version', '1.0.0'),
                ],
                'servers' => [['url' => config('api-starter.openapi.server_url', '/api')]],
                'tags' => [],
                'paths' => [],
                'components' => ['schemas' => [], 'securitySchemes' => []],
            ];
        }

        $index['tags'] = array_values(array_filter(
            $index['tags'] ?? [],
            fn ($t) => ($t['name'] ?? null) !== $tag
        ));
        $index['tags'][] = ['name' => $tag, 'description' => "Module resource {$tag}"];

        foreach ($resource['paths'] ?? [] as $k => $v) {
            $index['paths'][$k] = $v;
        }
        foreach ($resource['components']['schemas'] ?? [] as $k => $v) {
            $index['components']['schemas'][$k] = $v;
        }
        foreach ($resource['components']['securitySchemes'] ?? [] as $k => $v) {
            $index['components']['securitySchemes'][$k] = $v;
        }

        file_put_contents($indexPath, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }
}
