<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Console\InteractsWithStubs;
use Kindharika\ApiStarter\Support\AuthConfig;

class ApiMakeOpenApi extends Command
{
    use InteractsWithStubs;

    protected $signature = 'api:make-openapi
                            {name : The resource name}
                            {--auth : Mark operations as Sanctum-protected (sends Bearer in Swagger)}';

    protected $description = 'Generate OpenAPI 3 JSON for a resource (Swagger-ready)';

    public function handle(): int
    {
        if (! config('api-starter.openapi.enabled', true)) {
            $this->warn('OpenAPI generation disabled in config.');

            return self::SUCCESS;
        }

        $modelClass = Str::studly($this->argument('name'));
        $route = Str::kebab(Str::pluralStudly($modelClass));
        $dir = config('api-starter.paths.openapi', base_path('storage/api-docs'));
        $path = $dir . '/' . $route . '.openapi.json';
        $protected = (bool) $this->option('auth') || AuthConfig::enabled();

        $this->writeStub('openapi.stub.json', $path, [
            'title' => (string) config('api-starter.openapi.title', 'API Documentation'),
            'version' => (string) config('api-starter.openapi.version', '1.0.0'),
            'serverUrl' => (string) config('api-starter.openapi.server_url', '/api'),
            'modelClass' => $modelClass,
            'route' => $route,
        ]);

        if ($protected) {
            $this->applySecurityToFile($path);
        }

        $this->mergeOpenApiIndex($dir, $path, $modelClass, $route, $protected);

        $this->info("OpenAPI spec written to [{$path}]" . ($protected ? ' [auth required]' : ''));

        return self::SUCCESS;
    }

    protected function applySecurityToFile(string $path): void
    {
        $spec = json_decode((string) file_get_contents($path), true);
        if (! is_array($spec)) {
            return;
        }

        $spec = $this->applySecurityToSpec($spec);
        file_put_contents($path, json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    protected function applySecurityToSpec(array $spec): array
    {
        $spec['components'] ??= [];
        $spec['components']['securitySchemes']['sanctum'] = [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'Token',
            'description' => 'Paste token ONLY (no "Bearer " prefix). Example: 1|xxxxx',
        ];

        foreach ($spec['paths'] ?? [] as $pathKey => $operations) {
            if (! is_array($operations)) {
                continue;
            }
            foreach ($operations as $method => $operation) {
                if (! is_array($operation) || in_array($method, ['parameters', 'summary', 'description', 'servers'], true)) {
                    continue;
                }
                $spec['paths'][$pathKey][$method]['security'] = [['sanctum' => []]];
            }
        }

        return $spec;
    }

    protected function mergeOpenApiIndex(string $dir, string $resourcePath, string $modelClass, string $route, bool $protected): void
    {
        $indexPath = $dir . '/openapi.json';
        $resource = json_decode((string) file_get_contents($resourcePath), true);

        if (! is_array($resource)) {
            return;
        }

        $serverUrl = (string) config('api-starter.openapi.server_url', '/api');

        $index = file_exists($indexPath)
            ? json_decode((string) file_get_contents($indexPath), true)
            : null;

        if (! is_array($index)) {
            $index = [
                'openapi' => '3.0.3',
                'info' => [
                    'title' => config('api-starter.openapi.title', 'API Documentation'),
                    'version' => config('api-starter.openapi.version', '1.0.0'),
                ],
                'servers' => [
                    ['url' => $serverUrl, 'description' => 'Same origin (recommended)'],
                ],
                'tags' => [],
                'paths' => [],
                'components' => ['schemas' => [], 'securitySchemes' => []],
            ];
        }

        $index['servers'] = [
            ['url' => $serverUrl, 'description' => 'Same origin (recommended)'],
        ];

        $index['components']['securitySchemes']['sanctum'] = [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'Token',
            'description' => 'Paste token ONLY (no "Bearer " prefix). Example: 1|xxxxx',
        ];

        $index['tags'] = array_values(array_filter(
            $index['tags'] ?? [],
            fn ($tag) => ($tag['name'] ?? null) !== $modelClass
        ));
        $index['tags'][] = [
            'name' => $modelClass,
            'description' => 'CRUD endpoints for ' . $modelClass . ($protected ? ' (Sanctum)' : ''),
        ];

        foreach ($resource['paths'] ?? [] as $pathKey => $pathValue) {
            $index['paths'][$pathKey] = $pathValue;
        }

        foreach ($resource['components']['schemas'] ?? [] as $schemaKey => $schemaValue) {
            $index['components']['schemas'][$schemaKey] = $schemaValue;
        }

        file_put_contents(
            $indexPath,
            json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }
}
