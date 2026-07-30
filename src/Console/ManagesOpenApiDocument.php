<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console;

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

        foreach ($schemaKeysToReplace as $key) {
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
