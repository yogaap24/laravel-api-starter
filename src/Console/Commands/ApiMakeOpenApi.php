<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kindharika\ApiStarter\Console\InteractsWithStubs;
use Kindharika\ApiStarter\Console\ManagesOpenApiDocument;
use Kindharika\ApiStarter\Support\AuthConfig;
use Kindharika\ApiStarter\Support\ColumnSchema;

class ApiMakeOpenApi extends Command
{
    use InteractsWithStubs;
    use ManagesOpenApiDocument;

    protected $signature = 'api:make-openapi
                            {name : The resource name}
                            {--columns= : Column spec for OpenAPI schemas}
                            {--auth : Mark operations as Sanctum-protected (sends Bearer in Swagger)}';

    protected $description = 'Merge resource paths into storage/api-docs/openapi.json (single Swagger doc)';

    public function handle(): int
    {
        if (! config('api-starter.openapi.enabled', true)) {
            $this->warn('OpenAPI generation disabled in config.');

            return self::SUCCESS;
        }

        $modelClass = Str::studly($this->argument('name'));
        $route = Str::kebab(Str::pluralStudly($modelClass));
        $protected = (bool) $this->option('auth') || AuthConfig::enabled();

        try {
            $schema = ColumnSchema::parse((string) ($this->option('columns') ?: ''));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $fragment = $this->renderOpenApiStub('openapi.stub.json', [
            'title' => (string) config('api-starter.openapi.title', 'API Documentation'),
            'version' => (string) config('api-starter.openapi.version', '1.0.0'),
            'serverUrl' => (string) config('api-starter.openapi.server_url', '/api'),
            'modelClass' => $modelClass,
            'route' => $route,
            'searchColumnsExample' => $schema->openApiSearchColumnsExample(),
        ]);

        if ($fragment === null) {
            $this->error('Failed to render OpenAPI stub.');

            return self::FAILURE;
        }

        $fragment['components'] ??= [];
        $fragment['components']['schemas'] ??= [];
        $fragment['components']['schemas'][$modelClass] = $schema->openApiResourceSchema();
        $fragment['components']['schemas'][$modelClass . 'Store'] = $schema->openApiStoreSchema();
        $fragment['components']['schemas'][$modelClass . 'Update'] = $schema->openApiUpdateSchema();

        $this->mergeFragmentIntoOpenApi(
            fragment: $fragment,
            tagNamesToReplace: [$modelClass],
            pathBasesToReplace: [$route],
            schemaKeysToReplace: [$modelClass, $modelClass . 'Store', $modelClass . 'Update'],
            newTagName: $modelClass,
            newTagDescription: 'CRUD endpoints for ' . $modelClass . ($protected ? ' (Sanctum)' : ''),
            attachSanctumToFragmentPaths: $protected,
        );

        $this->info('OpenAPI updated: ' . $this->openApiIndexPath() . ($protected ? ' [auth required]' : ''));

        return self::SUCCESS;
    }
}
