<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console\Commands;

use Illuminate\Console\Command;
use Kindharika\ApiStarter\Console\InteractsWithStubs;
use Kindharika\ApiStarter\Console\ManagesOpenApiDocument;

class ApiOpenApiPrune extends Command
{
    use InteractsWithStubs;
    use ManagesOpenApiDocument;

    protected $signature = 'api:openapi-prune
                            {--prefix= : Remove paths under this prefix (e.g. course or blog/posts)}
                            {--schema= : Remove schema + Store/Update variants (comma-separated)}
                            {--orphans : Drop schemas/tags not used by any remaining path}
                            {--force : Skip confirmation}';

    protected $description = 'Clean leftover entries in storage/api-docs/openapi.json';

    public function handle(): int
    {
        if (! is_file($this->openApiIndexPath())) {
            $this->error('openapi.json not found at ' . $this->openApiIndexPath());

            return self::FAILURE;
        }

        $prefix = trim((string) $this->option('prefix'), '/');
        $schemaOpt = trim((string) $this->option('schema'));
        $orphans = (bool) $this->option('orphans');

        if ($prefix === '' && $schemaOpt === '' && ! $orphans) {
            $this->error('Pass --prefix= and/or --schema= and/or --orphans');
            $this->line('Examples:');
            $this->line('  php artisan api:openapi-prune --prefix=course');
            $this->line('  php artisan api:openapi-prune --schema=Course,Post');
            $this->line('  php artisan api:openapi-prune --orphans');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Update openapi.json?', true)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $schemas = $schemaOpt !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $schemaOpt))))
            : [];

        $stats = ['paths' => 0, 'tags' => 0, 'schemas' => 0];

        if ($prefix !== '' || $schemas !== []) {
            $stats = $this->removeOpenApiArtifacts(
                pathBases: $prefix !== '' ? [$prefix] : [],
                tags: [],
                schemas: $schemas,
            );
        } elseif ($orphans) {
            $index = json_decode((string) file_get_contents($this->openApiIndexPath()), true);
            if (! is_array($index)) {
                $this->error('Invalid openapi.json');

                return self::FAILURE;
            }
            $orphan = $this->pruneOrphanOpenApiArtifacts($index);
            $this->saveOpenApiIndex($index);
            $stats['tags'] = $orphan['tags'];
            $stats['schemas'] = $orphan['schemas'];
        }

        // Orphans after prefix/schema remove (extra sweep)
        if ($orphans && ($prefix !== '' || $schemas !== [])) {
            $index = json_decode((string) file_get_contents($this->openApiIndexPath()), true);
            if (is_array($index)) {
                $orphan = $this->pruneOrphanOpenApiArtifacts($index);
                $this->saveOpenApiIndex($index);
                $stats['tags'] += $orphan['tags'];
                $stats['schemas'] += $orphan['schemas'];
            }
        }

        $this->info(sprintf(
            'OpenAPI pruned: %d path(s), %d tag(s), %d schema(s)',
            $stats['paths'],
            $stats['tags'],
            $stats['schemas'],
        ));
        $this->line($this->openApiIndexPath());

        return self::SUCCESS;
    }
}
