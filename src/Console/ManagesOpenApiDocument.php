<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console;

use Illuminate\Support\Str;

/**
 * Single-document OpenAPI helpers — only storage/api-docs/openapi.json is kept.
 */
trait ManagesOpenApiDocument
{
    protected function openApiDirectory(): string
    {
        return (string) config('api-starter.paths.openapi', base_path('storage/api-docs'));
    }

    protected function openApiIndexPath(): string
    {
        return $this->openApiDirectory() . '/openapi.json';
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadOrCreateOpenApiIndex(): array
    {
        $dir = $this->openApiDirectory();
        $this->ensureDirectoryExists($dir);
        $indexPath = $this->openApiIndexPath();

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
                'servers' => [
                    ['url' => config('api-starter.openapi.server_url', '/api'), 'description' => 'Same origin'],
                ],
                'tags' => [],
                'paths' => [],
                'components' => ['schemas' => [], 'securitySchemes' => []],
            ];
        }

        $index['servers'] = [
            ['url' => (string) config('api-starter.openapi.server_url', '/api'), 'description' => 'Same origin'],
        ];
        $index['components'] ??= [];
        $index['components']['schemas'] ??= [];
        $index['components']['securitySchemes'] ??= [];
        $index['tags'] ??= [];
        $index['paths'] ??= [];

        return $index;
    }

    /**
     * @param  array<string, mixed>  $index
     */
    protected function saveOpenApiIndex(array $index): void
    {
        $path = $this->openApiIndexPath();
        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        $this->purgeOpenApiFragments();
    }

    /**
     * Delete legacy per-resource / per-module fragment specs. Keep only openapi.json.
     */
    protected function purgeOpenApiFragments(): void
    {
        $dir = $this->openApiDirectory();
        if (! is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*.openapi.json') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Remove paths (by prefix), tags, schemas — also harvest $ref + infer Model/Store/Update
     * from matching paths, then drop orphan schemas/tags no longer referenced.
     *
     * @param  list<string>  $pathBases
     * @param  list<string>  $tags
     * @param  list<string>  $schemas
     * @return array{paths: int, tags: int, schemas: int}
     */
    protected function removeOpenApiArtifacts(array $pathBases = [], array $tags = [], array $schemas = []): array
    {
        $indexPath = $this->openApiIndexPath();
        if (! is_file($indexPath)) {
            return ['paths' => 0, 'tags' => 0, 'schemas' => 0];
        }

        $index = json_decode((string) file_get_contents($indexPath), true);
        if (! is_array($index)) {
            return ['paths' => 0, 'tags' => 0, 'schemas' => 0];
        }

        $index['paths'] ??= [];
        $index['tags'] ??= [];
        $index['components'] ??= [];
        $index['components']['schemas'] ??= [];

        $pathBases = array_values(array_unique(array_filter(array_map(
            static fn ($b) => rtrim(ltrim((string) $b, '/'), '/'),
            $pathBases
        ))));

        $pathsToRemove = [];
        foreach (array_keys($index['paths']) as $pathKey) {
            $trimmed = ltrim((string) $pathKey, '/');
            foreach ($pathBases as $base) {
                if ($base === '') {
                    continue;
                }
                if ($trimmed === $base || str_starts_with($trimmed, $base . '/')) {
                    $pathsToRemove[] = $pathKey;
                    break;
                }
            }
        }

        $harvestedSchemas = [];
        $harvestedTags = [];
        foreach ($pathsToRemove as $pathKey) {
            $node = $index['paths'][$pathKey] ?? null;
            if (is_array($node)) {
                $harvestedSchemas = array_merge($harvestedSchemas, $this->collectOpenApiSchemaRefs($node));
                $harvestedTags = array_merge($harvestedTags, $this->collectOpenApiTags($node));
            }
            $harvestedSchemas = array_merge(
                $harvestedSchemas,
                $this->inferSchemaNamesFromPathKey((string) $pathKey)
            );
        }

        foreach ($pathBases as $base) {
            $harvestedSchemas = array_merge($harvestedSchemas, $this->inferSchemaNamesFromPathKey($base));
        }

        $schemaKeys = $this->expandOpenApiSchemaKeys(array_merge($schemas, $harvestedSchemas));
        $tagNames = array_values(array_unique(array_filter(array_merge($tags, $harvestedTags))));

        $removedPaths = 0;
        foreach ($pathsToRemove as $pathKey) {
            if (isset($index['paths'][$pathKey])) {
                unset($index['paths'][$pathKey]);
                $removedPaths++;
            }
        }

        $beforeTags = count($index['tags']);
        if ($tagNames !== []) {
            $index['tags'] = array_values(array_filter(
                $index['tags'],
                static fn ($t): bool => ! in_array($t['name'] ?? null, $tagNames, true)
            ));
        }
        $removedTags = $beforeTags - count($index['tags']);

        $removedSchemas = 0;
        foreach ($schemaKeys as $schema) {
            if (isset($index['components']['schemas'][$schema])) {
                unset($index['components']['schemas'][$schema]);
                $removedSchemas++;
            }
        }

        // Drop leftovers not used by remaining paths ($ref OR inferred from path segment)
        $orphan = $this->pruneOrphanOpenApiArtifacts($index);
        $removedTags += $orphan['tags'];
        $removedSchemas += $orphan['schemas'];

        $this->saveOpenApiIndex($index);

        return [
            'paths' => $removedPaths,
            'tags' => $removedTags,
            'schemas' => $removedSchemas,
        ];
    }

    /**
     * Remove schemas/tags that no path references anymore.
     *
     * @param  array<string, mixed>  $index
     * @return array{tags: int, schemas: int}
     */
    protected function pruneOrphanOpenApiArtifacts(array &$index): array
    {
        $index['paths'] ??= [];
        $index['tags'] ??= [];
        $index['components'] ??= [];
        $index['components']['schemas'] ??= [];

        // Model schemas often lack $ref on GET — also treat path segments as in-use
        $usedSchemas = array_values(array_unique(array_merge(
            $this->collectOpenApiSchemaRefs($index['paths']),
            $this->schemasImpliedByOpenApiPaths($index['paths']),
        )));
        $usedTags = $this->collectOpenApiTags($index['paths']);

        $keepSchemas = ['User', 'UserStore', 'UserUpdate'];

        $removedSchemas = 0;
        foreach (array_keys($index['components']['schemas']) as $name) {
            if (in_array($name, $keepSchemas, true)) {
                continue;
            }
            if (! in_array($name, $usedSchemas, true)) {
                unset($index['components']['schemas'][$name]);
                $removedSchemas++;
            }
        }

        $beforeTags = count($index['tags']);
        $index['tags'] = array_values(array_filter(
            $index['tags'],
            static function ($t) use ($usedTags): bool {
                $name = $t['name'] ?? null;
                if ($name === null) {
                    return false;
                }
                if (in_array($name, ['Auth', 'SSO', 'Authentication'], true)) {
                    return true;
                }

                return in_array($name, $usedTags, true);
            }
        ));

        return [
            'tags' => $beforeTags - count($index['tags']),
            'schemas' => $removedSchemas,
        ];
    }

    /**
     * @return list<string>
     */
    protected function collectOpenApiSchemaRefs(mixed $node): array
    {
        $found = [];
        if (is_array($node)) {
            if (isset($node['$ref']) && is_string($node['$ref'])) {
                if (preg_match('~^#/components/schemas/([^/]+)$~', $node['$ref'], $m)) {
                    $found[] = $m[1];
                }
            }
            foreach ($node as $child) {
                $found = array_merge($found, $this->collectOpenApiSchemaRefs($child));
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * @return list<string>
     */
    protected function collectOpenApiTags(mixed $node): array
    {
        $found = [];
        if (! is_array($node)) {
            return [];
        }

        foreach ($node as $method => $operation) {
            if (! is_array($operation) || in_array($method, ['parameters', 'summary', 'description', 'servers'], true)) {
                continue;
            }
            foreach ($operation['tags'] ?? [] as $tag) {
                if (is_string($tag) && $tag !== '') {
                    $found[] = $tag;
                }
            }
        }

        foreach ($node as $key => $child) {
            if (is_array($child) && is_string($key) && str_starts_with($key, '/')) {
                $found = array_merge($found, $this->collectOpenApiTags($child));
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * @param  array<string, mixed>  $paths
     * @return list<string>
     */
    protected function schemasImpliedByOpenApiPaths(array $paths): array
    {
        $out = [];
        foreach (array_keys($paths) as $pathKey) {
            $out = array_merge($out, $this->inferSchemaNamesFromPathKey((string) $pathKey));
        }

        return array_values(array_unique($out));
    }

    /**
     * /course → Course; /blog/posts/{id} → Post (+ Store/Update).
     *
     * @return list<string>
     */
    protected function inferSchemaNamesFromPathKey(string $pathKey): array
    {
        $parts = array_values(array_filter(
            explode('/', trim($pathKey, '/')),
            static fn (string $p): bool => $p !== '' && ! str_starts_with($p, '{')
        ));

        if ($parts === []) {
            return [];
        }

        $model = Str::studly(Str::singular((string) end($parts)));

        return $this->expandOpenApiSchemaKeys([$model]);
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    protected function expandOpenApiSchemaKeys(array $names): array
    {
        $out = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $base = preg_replace('/(Store|Update)$/', '', $name) ?: $name;
            $out[] = $base;
            $out[] = $base . 'Store';
            $out[] = $base . 'Update';
            $out[] = $name;
        }

        return array_values(array_unique($out));
    }

    /**
     * Merge a resource fragment (from stub, in-memory) into openapi.json.
     *
     * @param  array<string, mixed>  $fragment
     * @param  list<string>  $tagNamesToReplace
     * @param  list<string>  $pathBasesToReplace  e.g. ['course', 'course/courses']
     * @param  list<string>  $schemaKeysToReplace
     */
    protected function mergeFragmentIntoOpenApi(
        array $fragment,
        array $tagNamesToReplace = [],
        array $pathBasesToReplace = [],
        array $schemaKeysToReplace = [],
        ?string $newTagName = null,
        ?string $newTagDescription = null,
        bool $attachSanctumToFragmentPaths = false,
    ): void {
        $index = $this->loadOrCreateOpenApiIndex();

        if ($attachSanctumToFragmentPaths) {
            $index['components']['securitySchemes']['sanctum'] = [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'Token',
                'description' => 'Paste token ONLY (no "Bearer " prefix). Example: 1|xxxxx',
            ];
            foreach ($fragment['paths'] ?? [] as $p => $ops) {
                if (! is_array($ops)) {
                    continue;
                }
                foreach ($ops as $method => $op) {
                    if (! is_array($op) || in_array($method, ['parameters', 'summary', 'description', 'servers'], true)) {
                        continue;
                    }
                    $fragment['paths'][$p][$method]['security'] = [['sanctum' => []]];
                }
            }
        }

        $replaceTags = array_values(array_unique(array_filter($tagNamesToReplace)));
        if ($newTagName !== null) {
            $replaceTags[] = $newTagName;
        }
        foreach ($fragment['tags'] ?? [] as $tag) {
            if (isset($tag['name'])) {
                $replaceTags[] = $tag['name'];
            }
        }
        $replaceTags = array_values(array_unique($replaceTags));

        $index['tags'] = array_values(array_filter(
            $index['tags'] ?? [],
            static fn ($t): bool => ! in_array($t['name'] ?? null, $replaceTags, true)
        ));

        if ($newTagName !== null) {
            $index['tags'][] = [
                'name' => $newTagName,
                'description' => $newTagDescription ?? $newTagName,
            ];
        } else {
            foreach ($fragment['tags'] ?? [] as $tag) {
                if (is_array($tag) && isset($tag['name'])) {
                    $index['tags'][] = $tag;
                }
            }
        }

        foreach (array_keys($index['paths'] ?? []) as $existingPath) {
            $trimmed = ltrim((string) $existingPath, '/');
            foreach ($pathBasesToReplace as $base) {
                $base = ltrim((string) $base, '/');
                if ($base === '') {
                    continue;
                }
                if ($trimmed === $base || str_starts_with($trimmed, $base . '/')) {
                    unset($index['paths'][$existingPath]);
                    break;
                }
            }
        }

        foreach ($this->expandOpenApiSchemaKeys($schemaKeysToReplace) as $key) {
            unset($index['components']['schemas'][$key]);
        }

        foreach ($fragment['paths'] ?? [] as $k => $v) {
            $index['paths'][$k] = $v;
        }
        foreach ($fragment['components']['schemas'] ?? [] as $k => $v) {
            $index['components']['schemas'][$k] = $v;
        }
        foreach ($fragment['components']['securitySchemes'] ?? [] as $k => $v) {
            $index['components']['securitySchemes'][$k] = $v;
        }

        $this->saveOpenApiIndex($index);
    }

    /**
     * Render stub replacements to an OpenAPI fragment array (no disk fragment file).
     *
     * @param  array<string, string>  $replacements
     * @return array<string, mixed>|null
     */
    protected function renderOpenApiStub(string $stubFile, array $replacements): ?array
    {
        $json = $this->renderStub($stubFile, $replacements);
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }
}
