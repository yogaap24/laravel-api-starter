<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Console\BuildsColumnReplacements;
use Kindharika\ApiStarter\Console\InteractsWithStubs;
use Kindharika\ApiStarter\Console\ManagesOpenApiDocument;
use Kindharika\ApiStarter\Modules\ModulePaths;
use Kindharika\ApiStarter\Modules\ResourceRouteMarker;
use Kindharika\ApiStarter\Support\AuthConfig;
use Kindharika\ApiStarter\Support\ColumnSchema;

class ModuleScaffold extends Command
{
    use BuildsColumnReplacements;
    use InteractsWithStubs;
    use ManagesOpenApiDocument;

    protected $signature = 'module:scaffold
                            {module : Module name (also resource name if {name} omitted)}
                            {name? : Resource name — omit to reuse module name}
                            {--columns= : Column spec e.g. name:string,status:enum:draft|published}
                            {--migrate : Run migrations after generation}
                            {--force : Overwrite existing files}
                            {--auth : Protect resource with Sanctum}
                            {--public : Force public routes}
                            {--audit : Attach Auditable trait (observer audit trail)}
                            {--permission= : RBAC permission middleware (e.g. posts.manage)}
                            {--role= : RBAC role middleware (e.g. admin)}
                            {--no-openapi : Skip OpenAPI generation}';

    protected $description = 'Scaffold CRUD resource inside an API module (one name OK: module:scaffold Course)';

    public function handle(): int
    {
        if (! config('api-starter.modules.enabled', true)) {
            $this->error('Modules disabled in config.');

            return self::FAILURE;
        }

        $module = Str::studly($this->argument('module'));
        $nameArg = $this->argument('name');
        $name = $nameArg !== null && $nameArg !== ''
            ? Str::studly((string) $nameArg)
            : $module;

        if (! ModulePaths::exists($module)) {
            $this->warn("Module [{$module}] missing — creating skeleton…");
            $exit = $this->call('module:make', ['name' => $module]);
            if ($exit !== self::SUCCESS) {
                return $exit;
            }
        }

        try {
            $schema = ColumnSchema::resolve(
                $this->option('columns') !== null ? (string) $this->option('columns') : null,
                $this
            );
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($schema->columns as $col) {
            if (in_array($col->type, ['enum', 'set'], true) && ($col->enumValues ?? []) === []) {
                $this->warn("Column [{$col->name}] bare {$col->type} → string(64). Prefer: {$col->name}:{$col->type}:a;b;c (quote --columns)");
            }
        }

        $audit = (bool) $this->option('audit') || (bool) config('api-starter.audit.enabled', false);
        $cols = $this->columnReplacements($schema, $audit);

        $moduleNs = ModulePaths::namespace($module);
        $moduleDir = ModulePaths::module($module);
        $force = (bool) $this->option('force');
        $protected = $this->resolveProtected();
        $table = Str::snake(Str::pluralStudly($name));
        $primary = $this->isPrimaryResource($module, $name);
        // Primary (module:scaffold Course) → /api/course — not /api/course/courses
        $routeUri = $primary ? '' : Str::kebab(Str::pluralStudly($name));
        $controller = $name . 'Controller';
        $modulePrefix = ModulePaths::prefix($module);
        $openApiPath = $primary ? $modulePrefix : $modulePrefix . '/' . $routeUri;

        $this->line('Columns: ' . implode(', ', $schema->names()));
        if ($primary) {
            $this->comment("Primary resource → URL /{$modulePrefix} (no duplicated segment)");
        }

        $files = [
            [
                'stub' => 'module/model.stub',
                'path' => "{$moduleDir}/Models/{$name}.php",
                'vars' => array_merge([
                    'namespace' => $moduleNs . '\\Models',
                    'class' => $name,
                    'table' => $table,
                ], $cols),
            ],
            [
                'stub' => 'module/controller.stub',
                'path' => "{$moduleDir}/Http/Controllers/{$controller}.php",
                'vars' => array_merge([
                    'namespace' => $moduleNs,
                    'class' => $controller,
                    'modelClass' => $name,
                ], $cols),
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
                'vars' => array_merge([
                    'namespace' => $moduleNs,
                    'class' => "Store{$name}Request",
                    'modelClass' => $name,
                ], $cols),
            ],
            [
                'stub' => 'module/request.update.stub',
                'path' => "{$moduleDir}/Http/Requests/{$name}/Update{$name}Request.php",
                'vars' => array_merge([
                    'namespace' => $moduleNs,
                    'class' => "Update{$name}Request",
                    'modelClass' => $name,
                ], $cols),
            ],
            [
                'stub' => 'module/resource.stub',
                'path' => "{$moduleDir}/Http/Resources/{$name}Resource.php",
                'vars' => array_merge([
                    'namespace' => $moduleNs . '\\Http\\Resources',
                    'class' => $name . 'Resource',
                ], $cols),
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

        $this->writeMigration($table, $cols, $force);
        $this->appendRoute($moduleDir, $moduleNs, $controller, $name, $routeUri, $protected);
        $this->writeOpenApi($module, $name, $routeUri, $primary, $protected, $schema);

        if ($audit && ! config('api-starter.audit.enabled', false)) {
            $this->warn('Model uses Auditable but API_STARTER_AUDIT=false — enable + run api:make-audit.');
        }

        if ($this->option('migrate')) {
            $this->call('migrate');
        }

        $prefix = trim((string) config('api-starter.route_prefix', 'api'), '/');
        $baseUrl = rtrim((string) config('app.url', 'http://localhost'), '/');
        $resourceUrl = "{$baseUrl}/{$prefix}/{$openApiPath}";

        $this->newLine();
        $this->info("Module scaffold [{$module}/{$name}] done.");
        $this->line("  GET/POST {$resourceUrl}");
        $this->line('Migration: database/migrations (standard path)');
        $this->line('Auth: ' . ($protected ? 'ON' : 'OFF') . ' | Audit: ' . ($audit ? 'ON' : 'OFF'));

        return self::SUCCESS;
    }

    protected function isPrimaryResource(string $module, string $name): bool
    {
        return Str::studly($module) === Str::studly($name);
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

    /**
     * @param  array<string, string>  $cols
     */
    protected function writeMigration(string $table, array $cols, bool $force): void
    {
        $dir = config('api-starter.paths.migration', database_path('migrations'));
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
        $this->writeStub('migration.stub', $dir . '/' . $filename, array_merge([
            'table' => $table,
        ], $cols));
        $this->info("Created: {$dir}/{$filename}");
    }

    protected function appendRoute(
        string $moduleDir,
        string $moduleNs,
        string $controller,
        string $resourceName,
        string $routeUri,
        bool $protected,
    ): void {
        // Single file: Routes/api.php — auth/RBAC as middleware inside the block
        $file = $moduleDir . '/Routes/api.php';
        $legacyProtected = $moduleDir . '/Routes/api-protected.php';

        if (is_file($legacyProtected)) {
            $this->removeResourceRoutes($legacyProtected, $resourceName, $routeUri);
        }

        $controllerFqcn = $moduleNs . '\\Http\\Controllers\\' . $controller;
        $middlewareParts = [];

        if ($protected) {
            foreach (AuthConfig::middleware() as $mw) {
                $middlewareParts[] = $mw;
            }
        }
        if ($permission = $this->option('permission')) {
            $middlewareParts[] = "api-starter.permission:{$permission}";
        }
        if ($role = $this->option('role')) {
            $middlewareParts[] = "api-starter.role:{$role}";
        }

        $block = $this->buildRouteBlock($controllerFqcn, $resourceName, $routeUri, $middlewareParts);

        $contents = is_file($file)
            ? (string) file_get_contents($file)
            : "<?php\n\ndeclare(strict_types=1);\n\nuse Illuminate\\Support\\Facades\\Route;\n\n";

        if (! str_contains($contents, 'use Illuminate\\Support\\Facades\\Route;')) {
            $contents = preg_replace('/^<\?php\s*/', "<?php\n\ndeclare(strict_types=1);\n\nuse Illuminate\\Support\\Facades\\Route;\n\n", $contents, 1) ?? $contents;
        }

        $contents = $this->stripResourceRoutes($contents, $resourceName, $routeUri);
        $contents = rtrim($contents) . "\n" . $block . "\n";

        file_put_contents($file, $contents);
        $this->info('Updated routes: ' . $file . ($protected ? ' [auth:sanctum]' : ' [public]'));
    }

    /**
     * @param  list<string>  $middlewareParts
     */
    protected function buildRouteBlock(
        string $controllerFqcn,
        string $resourceName,
        string $routeUri,
        array $middlewareParts,
    ): string {
        if ($routeUri === '') {
            $inner = implode("\n", [
                "    Route::get('/', [\\{$controllerFqcn}::class, 'index']);",
                "    Route::post('/', [\\{$controllerFqcn}::class, 'store']);",
                "    Route::get('{id}', [\\{$controllerFqcn}::class, 'show']);",
                "    Route::put('{id}', [\\{$controllerFqcn}::class, 'update']);",
                "    Route::patch('{id}', [\\{$controllerFqcn}::class, 'update']);",
                "    Route::delete('{id}', [\\{$controllerFqcn}::class, 'destroy']);",
            ]);

            if ($middlewareParts !== []) {
                $mw = implode("','", $middlewareParts);

                return ResourceRouteMarker::wrap(
                    $resourceName,
                    "Route::middleware(['{$mw}'])->group(function () {\n{$inner}\n});"
                );
            }

            $flat = implode("\n", [
                "Route::get('/', [\\{$controllerFqcn}::class, 'index']);",
                "Route::post('/', [\\{$controllerFqcn}::class, 'store']);",
                "Route::get('{id}', [\\{$controllerFqcn}::class, 'show']);",
                "Route::put('{id}', [\\{$controllerFqcn}::class, 'update']);",
                "Route::patch('{id}', [\\{$controllerFqcn}::class, 'update']);",
                "Route::delete('{id}', [\\{$controllerFqcn}::class, 'destroy']);",
            ]);

            return ResourceRouteMarker::wrap($resourceName, $flat);
        }

        if ($middlewareParts !== []) {
            $mw = implode("','", $middlewareParts);
            $line = "Route::middleware(['{$mw}'])->apiResource('{$routeUri}', \\{$controllerFqcn}::class);";
        } else {
            $line = "Route::apiResource('{$routeUri}', \\{$controllerFqcn}::class);";
        }

        return ResourceRouteMarker::wrap($resourceName, $line);
    }

    protected function removeResourceRoutes(string $file, string $resourceName, string $routeUri): void
    {
        if (! is_file($file)) {
            return;
        }
        $contents = (string) file_get_contents($file);
        $updated = $this->stripResourceRoutes($contents, $resourceName, $routeUri);
        if ($updated !== $contents) {
            file_put_contents($file, $updated);
        }
    }

    protected function stripResourceRoutes(string $contents, string $resourceName, string $routeUri): string
    {
        return ResourceRouteMarker::strip($contents, $resourceName, [
            $routeUri,
            Str::kebab(Str::pluralStudly($resourceName)),
            Str::kebab(Str::studly($resourceName)),
        ]);
    }

    protected function writeOpenApi(
        string $module,
        string $name,
        string $routeUri,
        bool $primary,
        bool $protected,
        ColumnSchema $schema,
    ): void {
        if ($this->option('no-openapi') || ! config('api-starter.openapi.enabled', true)) {
            return;
        }

        $modulePrefix = ModulePaths::prefix($module);
        $pathKey = $primary || $routeUri === ''
            ? $modulePrefix
            : $modulePrefix . '/' . $routeUri;

        $tag = $primary ? $module : ($module . '/' . $name);
        $opId = $primary ? $module : ($module . $name);

        $fragment = $this->renderOpenApiStub('module/openapi.stub.json', [
            'title' => (string) config('api-starter.openapi.title', 'API Documentation'),
            'version' => (string) config('api-starter.openapi.version', '1.0.0'),
            'serverUrl' => (string) config('api-starter.openapi.server_url', '/api'),
            'modelClass' => $name,
            'module' => $module,
            'tag' => $tag,
            'opId' => $opId,
            'route' => $pathKey,
            'searchColumnsExample' => $schema->openApiSearchColumnsExample(),
        ]);

        if ($fragment === null) {
            $this->error('Failed to render module OpenAPI stub.');

            return;
        }

        $fragment['components'] ??= [];
        $fragment['components']['schemas'] ??= [];
        $fragment['components']['schemas'][$name] = $schema->openApiResourceSchema();
        $fragment['components']['schemas'][$name . 'Store'] = $schema->openApiStoreSchema();
        $fragment['components']['schemas'][$name . 'Update'] = $schema->openApiUpdateSchema();

        $pathBases = [$pathKey];
        if ($primary) {
            $pathBases[] = $modulePrefix . '/' . Str::kebab(Str::pluralStudly($name));
            $pathBases[] = $modulePrefix . '/' . Str::kebab(Str::studly($name));
        }

        $this->mergeFragmentIntoOpenApi(
            fragment: $fragment,
            tagNamesToReplace: [
                $tag,
                $modulePrefix,
                $module . '/' . $name,
                $name . '/' . $name,
            ],
            pathBasesToReplace: $pathBases,
            schemaKeysToReplace: [$name, $name . 'Store', $name . 'Update'],
            newTagName: $tag,
            newTagDescription: "Module resource {$tag}",
            attachSanctumToFragmentPaths: $protected,
        );

        $this->info('OpenAPI updated: ' . $this->openApiIndexPath());
    }
}
