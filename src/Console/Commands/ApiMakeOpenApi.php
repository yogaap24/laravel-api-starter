<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Console\InteractsWithStubs;

class ApiMakeOpenApi extends Command
{
    use InteractsWithStubs;

    protected $signature = 'api:make-openapi {name}';

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

        $this->writeStub('openapi.stub.json', $path, [
            'title' => (string) config('api-starter.openapi.title', 'API Documentation'),
            'version' => (string) config('api-starter.openapi.version', '1.0.0'),
            'serverUrl' => (string) config('api-starter.openapi.server_url', url('/api')),
            'modelClass' => $modelClass,
            'route' => $route,
        ]);

        $this->mergeOpenApiIndex($dir, $path, $modelClass, $route);

        $this->info("OpenAPI spec written to [{$path}]");

        return self::SUCCESS;
    }

    protected function mergeOpenApiIndex(string $dir, string $resourcePath, string $modelClass, string $route): void
    {
        $indexPath = $dir . '/openapi.json';
        $resource = json_decode((string) file_get_contents($resourcePath), true);

        if (! is_array($resource)) {
            return;
        }

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
                    ['url' => config('api-starter.openapi.server_url', url('/api'))],
                ],
                'tags' => [],
                'paths' => [],
                'components' => ['schemas' => []],
            ];
        }

        $index['tags'] = array_values(array_filter(
            $index['tags'] ?? [],
            fn ($tag) => ($tag['name'] ?? null) !== $modelClass
        ));
        $index['tags'][] = [
            'name' => $modelClass,
            'description' => "CRUD endpoints for {$modelClass}",
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
